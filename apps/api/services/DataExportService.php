<?php

declare(strict_types=1);

namespace app\services;

use app\components\CsvExporter;
use app\components\OrganisationTime;
use app\components\ReceiptStorage;
use app\components\TenantContext;
use app\models\Expense;
use app\models\Learner;
use app\models\Lesson;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use ZipArchive;

class DataExportService
{
    private BusinessFinanceService $finance;

    public function __construct(?BusinessFinanceService $finance = null)
    {
        $this->finance = $finance ?? new BusinessFinanceService();
    }

    public function pupilsCsv(): string
    {
        $this->requireOrganisation();
        /** @var Learner[] $learners */
        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->orderBy(['last_name' => SORT_ASC, 'first_name' => SORT_ASC])
            ->all();

        $rows = [];
        foreach ($learners as $learner) {
            $rows[] = [
                (string) (int) $learner->id,
                $learner->first_name,
                $learner->last_name,
                (string) ($learner->email ?? ''),
                $learner->mobile,
                $learner->lifecycle,
                (string) ($learner->default_pickup_address ?? ''),
                (string) ($learner->test_date ?? ''),
                (string) ($learner->test_centre ?? ''),
                substr((string) $learner->created_at, 0, 10),
            ];
        }

        return CsvExporter::build([
            'pupil_id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'status',
            'default_pickup',
            'test_date',
            'test_centre',
            'created_date',
        ], $rows);
    }

    /**
     * @return array{content: string, filename: string, mime: string}
     */
    public function exportByType(string $type, ?string $from = null, ?string $to = null): array
    {
        $filename = 'ownlane-' . $type;
        if ($from && $to) {
            $filename .= '-' . $from . '-to-' . $to;
        }
        $filename .= '.csv';

        $content = match ($type) {
            'pupils' => $this->pupilsCsv(),
            'payments' => $this->finance->exportPaymentsCsv($from, $to),
            'expenses' => $this->finance->exportExpensesCsv($from, $to),
            'mileage' => $this->finance->exportMileageCsv($from, $to),
            'lessons' => $this->finance->exportLessonsCsv($from, $to),
            'teaching_income' => $this->finance->exportTeachingIncomeCsv($from, $to),
            default => throw new BadRequestHttpException('Unknown export type.'),
        };

        return [
            'content' => $content,
            'filename' => $filename,
            'mime' => 'text/csv; charset=UTF-8',
        ];
    }

    /**
     * @return array{content: string, filename: string, mime: string}
     */
    public function accountantPack(?string $from = null, ?string $to = null): array
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toInclusive] = $this->resolveRange($org, $from, $to);
        $fromDay = $fromLocal->format('Y-m-d');
        $toDay = $toInclusive->format('Y-m-d');

        if (!class_exists(ZipArchive::class)) {
            throw new BadRequestHttpException('ZIP export is not available on this server.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ownlane-pack-');
        if ($tmp === false) {
            throw new BadRequestHttpException('Could not create export file.');
        }
        $zipPath = $tmp . '.zip';
        rename($tmp, $zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BadRequestHttpException('Could not create export archive.');
        }

        $zip->addFromString('payments.csv', $this->finance->exportPaymentsCsv($fromDay, $toDay));
        $zip->addFromString('expenses.csv', $this->finance->exportExpensesCsv($fromDay, $toDay));
        $zip->addFromString('mileage.csv', $this->finance->exportMileageCsv($fromDay, $toDay));
        $zip->addFromString('teaching-income.csv', $this->finance->exportTeachingIncomeCsv($fromDay, $toDay));

        $this->addReceiptsToZip($zip, $org, $fromDay, $toDay);

        $zip->close();
        $content = file_get_contents($zipPath);
        unlink($zipPath);
        if ($content === false) {
            throw new BadRequestHttpException('Could not read export archive.');
        }

        return [
            'content' => $content,
            'filename' => 'ownlane-accountant-' . $fromDay . '-to-' . $toDay . '.zip',
            'mime' => 'application/zip',
        ];
    }

    /**
     * @return array{from: string, to: string, range_label: string, includes: list<string>}
     */
    public function accountantPackSummary(?string $from = null, ?string $to = null): array
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toInclusive] = $this->resolveRange($org, $from, $to);

        return [
            'from' => $fromLocal->format('Y-m-d'),
            'to' => $toInclusive->format('Y-m-d'),
            'range_label' => $fromLocal->format('j M Y') . ' – ' . $toInclusive->format('j M Y'),
            'includes' => [
                'Teaching income',
                'Payments',
                'Expenses',
                'Mileage',
                'Receipt attachments (where available)',
            ],
        ];
    }

    private function addReceiptsToZip(ZipArchive $zip, Organisation $org, string $fromDay, string $toDay): void
    {
        /** @var Expense[] $expenses */
        $expenses = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'spent_on', $fromDay])
            ->andWhere(['<=', 'spent_on', $toDay])
            ->andWhere(['not', ['receipt_path' => null]])
            ->all();

        $storage = new ReceiptStorage();
        foreach ($expenses as $expense) {
            if ($expense->receipt_path === null) {
                continue;
            }
            try {
                $bytes = $storage->read((string) $expense->receipt_path);
            } catch (NotFoundHttpException) {
                continue;
            }
            $ext = pathinfo((string) $expense->receipt_path, PATHINFO_EXTENSION) ?: 'bin';
            $zip->addFromString('receipts/expense-' . (int) $expense->id . '.' . $ext, $bytes);
        }
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function resolveRange(Organisation $org, ?string $fromDate, ?string $toDate): array
    {
        $tz = OrganisationTime::timezoneFor($org);
        $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);

        if ($fromDate === null || trim($fromDate) === '') {
            $year = (int) $today->format('Y');
            $taxStartThis = new DateTimeImmutable($year . '-04-06', $tz);
            if ($today < $taxStartThis) {
                $fromLocal = new DateTimeImmutable(($year - 1) . '-04-06', $tz);
            } else {
                $fromLocal = $taxStartThis;
            }
        } else {
            $fromLocal = $this->parseDay(trim($fromDate), $tz);
        }

        if ($toDate === null || trim($toDate) === '') {
            $toInclusive = $today;
        } else {
            $toInclusive = $this->parseDay(trim($toDate), $tz);
        }

        if ($toInclusive < $fromLocal) {
            throw new BadRequestHttpException('End date must be on or after the start date.');
        }

        return [$fromLocal, $toInclusive];
    }

    private function parseDay(string $ymd, DateTimeZone $tz): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, $tz);
        if ($dt === false) {
            throw new BadRequestHttpException('Dates must be YYYY-MM-DD.');
        }

        return $dt->setTime(0, 0, 0);
    }

    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $org;
    }
}
