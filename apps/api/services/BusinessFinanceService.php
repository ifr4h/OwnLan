<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\ReceiptStorage;
use app\components\TeachingValueResolver;
use app\components\TenantContext;
use app\models\Expense;
use app\models\Learner;
use app\models\Lesson;
use app\models\LessonCharge;
use app\models\MileageLog;
use app\models\Organisation;
use app\models\Payment;
use app\models\Vehicle;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;

/**
 * Lean business money overview for an independent instructor.
 *
 * Reads live payments / charges / expenses — does not copy totals into dashboard tables.
 */
class BusinessFinanceService
{
    private AccountsOverviewService $accounts;

    public function __construct(?AccountsOverviewService $accounts = null)
    {
        $this->accounts = $accounts ?? new AccountsOverviewService();
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(?string $fromDate = null, ?string $toDate = null): array
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toLocalExclusive, $fromLabel, $toLabel] = $this->resolveRange($org, $fromDate, $toDate);
        $fromUtc = $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = $toLocalExclusive->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $fromDay = $fromLocal->format('Y-m-d');
        $toDayInclusive = $toLocalExclusive->modify('-1 day')->format('Y-m-d');

        $incomePence = $this->sumRecordedIncome($fromUtc, $toUtc);
        $spendingPence = $this->sumSpending($fromDay, $toDayInclusive);
        $outstandingPence = $this->sumOutstanding();
        $profitPence = $incomePence - $spendingPence;

        $base = [
            'from' => $fromDay,
            'to' => $toDayInclusive,
            'from_label' => $fromLabel,
            'to_label' => $toLabel,
            'range_label' => $fromLabel . ' – ' . $toLabel,
            'money_received_pence' => $incomePence,
            'money_received_label' => Money::formatPence($incomePence),
            'still_owed_pence' => $outstandingPence,
            'still_owed_label' => Money::formatPence($outstandingPence),
            'spending_pence' => $spendingPence,
            'spending_label' => Money::formatPence($spendingPence),
            'left_after_spending_pence' => $profitPence,
            'left_after_spending_label' => Money::formatPence($profitPence),
            'income_count' => $this->countRecordedIncome($fromUtc, $toUtc),
            'payments_received_breakdown' => $this->paymentsReceivedBreakdown($fromUtc, $toUtc),
            'expense_count' => $this->countSpending($fromDay, $toDayInclusive),
            'who_owes' => $this->whoOwes(12),
            'recent_income' => $this->recentIncome($fromUtc, $toUtc, 12),
            'expenses' => $this->listExpenses($fromDay, $toDayInclusive),
            'spending_by_category' => $this->spendingByCategory($fromDay, $toDayInclusive),
            'categories' => $this->categoryOptions(),
            'presets' => $this->presetOptions($org),
            'vehicles' => $this->vehicleOptions(),
            'payment_methods' => $this->paymentMethodOptions(),
        ];

        return $this->accounts->enrichOverview(
            $org,
            $fromLocal,
            new DateTimeImmutable($toDayInclusive, OrganisationTime::timezoneFor($org)),
            $base,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createExpense(array $data): array
    {
        $org = $this->requireOrganisation();
        $amountPence = $this->resolveAmountPence($data);
        $category = trim((string) ($data['category'] ?? ''));
        if (!in_array($category, Expense::categories(), true)) {
            throw new BadRequestHttpException('Choose a spending category.');
        }
        $spentOn = trim((string) ($data['spent_on'] ?? ''));
        if ($spentOn === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $spentOn)) {
            throw new BadRequestHttpException('spent_on must be YYYY-MM-DD.');
        }
        $notes = $this->nullableText($data['notes'] ?? null);
        $supplier = $this->nullableText($data['supplier'] ?? null);
        $paymentMethod = $this->nullableText($data['payment_method'] ?? null);
        $vehicleId = isset($data['vehicle_id']) && $data['vehicle_id'] !== ''
            ? (int) $data['vehicle_id']
            : null;
        if ($vehicleId !== null) {
            $this->assertVehicleBelongsToOrg($vehicleId);
        }

        $now = gmdate('Y-m-d H:i:s');
        $expense = new Expense();
        $expense->organisation_id = (int) $org->id;
        $expense->amount_pence = $amountPence;
        $expense->category = $category;
        $expense->supplier = $supplier;
        $expense->payment_method = $paymentMethod;
        $expense->vehicle_id = $vehicleId;
        $expense->spent_on = $spentOn;
        $expense->notes = $notes;
        $expense->created_by_user_id = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $expense->created_at = $now;
        $expense->updated_at = $now;

        if (!$expense->save()) {
            throw new BadRequestHttpException($this->firstError($expense));
        }

        return $this->serializeExpense($expense);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function voidExpense(int $id, array $data = []): array
    {
        /** @var Expense|null $expense */
        $expense = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['id' => $id])
            ->one();
        if ($expense === null) {
            throw new NotFoundHttpException('Expense not found.');
        }
        if ($expense->voided_at !== null) {
            throw new BadRequestHttpException('This spending entry is already voided.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new BadRequestHttpException('A reason is required to void spending.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $expense->voided_at = $now;
        $expense->void_reason = $reason;
        $expense->updated_at = $now;
        if (!$expense->save(true, ['voided_at', 'void_reason', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($expense));
        }

        return $this->serializeExpense($expense);
    }

    /**
     * @return array<string, mixed>
     */
    public function attachReceipt(int $expenseId, ?UploadedFile $file): array
    {
        if ($file === null) {
            throw new BadRequestHttpException('Choose a receipt file to upload.');
        }
        $org = $this->requireOrganisation();
        /** @var Expense|null $expense */
        $expense = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['id' => $expenseId])
            ->one();
        if ($expense === null) {
            throw new NotFoundHttpException('Expense not found.');
        }

        $storage = new ReceiptStorage();
        if ($expense->receipt_path) {
            $storage->delete((string) $expense->receipt_path);
        }
        $stored = $storage->store((int) $org->id, (int) $expense->id, $file);
        $expense->receipt_path = $stored['path'];
        $expense->receipt_original_name = $stored['original_name'];
        $expense->updated_at = gmdate('Y-m-d H:i:s');
        if (!$expense->save(true, ['receipt_path', 'receipt_original_name', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($expense));
        }

        return $this->serializeExpense($expense);
    }

    public function receiptContents(int $expenseId): array
    {
        /** @var Expense|null $expense */
        $expense = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['id' => $expenseId])
            ->one();
        if ($expense === null || $expense->receipt_path === null) {
            throw new NotFoundHttpException('Receipt not found.');
        }
        $storage = new ReceiptStorage();

        return [
            'content' => $storage->read((string) $expense->receipt_path),
            'mime' => $storage->mimeType((string) $expense->receipt_path),
            'filename' => $expense->receipt_original_name ?? 'receipt',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function report(?string $fromDate = null, ?string $toDate = null): array
    {
        $overview = $this->overview($fromDate, $toDate);

        return [
            'from' => $overview['from'],
            'to' => $overview['to'],
            'range_label' => $overview['range_label'],
            'teaching_income_pence' => $overview['teaching_income_pence'],
            'teaching_income_label' => $overview['teaching_income_label'],
            'money_received_pence' => $overview['money_received_pence'],
            'money_received_label' => $overview['money_received_label'],
            'still_owed_pence' => $overview['still_owed_pence'],
            'still_owed_label' => $overview['still_owed_label'],
            'spending_pence' => $overview['spending_pence'],
            'spending_label' => $overview['spending_label'],
            'difference_pence' => (int) $overview['teaching_income_pence'] - (int) $overview['spending_pence'],
            'difference_label' => Money::formatPence(
                (int) $overview['teaching_income_pence'] - (int) $overview['spending_pence'],
            ),
            'average_teaching_value' => $overview['average_teaching_value'],
            'monthly_trend' => $overview['monthly_trend'],
            'spending_by_category' => $overview['spending_by_category'],
            'export_types' => [
                ['id' => 'payments', 'label' => 'Payments'],
                ['id' => 'expenses', 'label' => 'Expenses'],
                ['id' => 'mileage', 'label' => 'Mileage'],
                ['id' => 'lessons', 'label' => 'Lessons'],
                ['id' => 'teaching_income', 'label' => 'Teaching income'],
                ['id' => 'combined', 'label' => 'Combined money export'],
            ],
        ];
    }

    public function exportByType(string $type, ?string $fromDate = null, ?string $toDate = null): string
    {
        return match ($type) {
            'payments' => $this->exportPaymentsCsv($fromDate, $toDate),
            'expenses' => $this->exportExpensesCsv($fromDate, $toDate),
            'mileage' => $this->exportMileageCsv($fromDate, $toDate),
            'lessons' => $this->exportLessonsCsv($fromDate, $toDate),
            'teaching_income' => $this->exportTeachingIncomeCsv($fromDate, $toDate),
            'combined', 'money' => $this->exportCsv($fromDate, $toDate),
            default => throw new BadRequestHttpException('Unknown export type.'),
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsertGoal(array $data): array
    {
        $org = $this->requireOrganisation();
        $year = (int) ($data['period_year'] ?? 0);
        $month = (int) ($data['period_month'] ?? 0);
        $targetPence = isset($data['target_pence'])
            ? (int) $data['target_pence']
            : Money::poundsToPence((string) ($data['target'] ?? '0'));

        return $this->accounts->upsertGoal($org, $year, $month, $targetPence);
    }

  /**
     * @param array<string, mixed> $data
     */
    public function deleteGoal(array $data): void
    {
        $org = $this->requireOrganisation();
        $this->accounts->deleteGoal(
            $org,
            (int) ($data['period_year'] ?? 0),
            (int) ($data['period_month'] ?? 0),
        );
    }

    /**
     * CSV of money received + spending in range, plus a still-owed snapshot.
     */
    public function exportCsv(?string $fromDate = null, ?string $toDate = null): string
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toLocalExclusive] = $this->resolveRange($org, $fromDate, $toDate);
        $fromUtc = $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = $toLocalExclusive->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $fromDay = $fromLocal->format('Y-m-d');
        $toDayInclusive = $toLocalExclusive->modify('-1 day')->format('Y-m-d');

        $lines = [];
        $lines[] = $this->csvRow(['type', 'date', 'who_or_category', 'method_or_notes', 'amount_pounds', 'amount_pence', 'counts_as_income', 'voided']);

        /** @var Payment[] $payments */
        $payments = TenantContext::scopeByOrganisation(Payment::find())
            ->andWhere(['counts_as_income' => true])
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'recorded_at', $fromUtc])
            ->andWhere(['<', 'recorded_at', $toUtc])
            ->with(['learner'])
            ->orderBy(['recorded_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($payments as $payment) {
            $local = OrganisationTime::utcToLocal($payment->recorded_at, $org);
            $learner = $payment->learner;
            $lines[] = $this->csvRow([
                'money_received',
                $local->format('Y-m-d'),
                $learner?->fullName ?? ('Pupil #' . (int) $payment->learner_id),
                $payment->method . ($payment->notes ? ' · ' . $payment->notes : ''),
                $this->penceAsPoundsField((int) $payment->amount_pence),
                (string) (int) $payment->amount_pence,
                'yes',
                '',
            ]);
        }

        /** @var Expense[] $expenses */
        $expenses = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'spent_on', $fromDay])
            ->andWhere(['<=', 'spent_on', $toDayInclusive])
            ->orderBy(['spent_on' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($expenses as $expense) {
            $lines[] = $this->csvRow([
                'spending',
                $expense->spent_on,
                Expense::categoryLabel($expense->category),
                (string) ($expense->notes ?? ''),
                $this->penceAsPoundsField((int) $expense->amount_pence),
                (string) (int) $expense->amount_pence,
                '',
                '',
            ]);
        }

        foreach ($this->whoOwes(500) as $row) {
            $lines[] = $this->csvRow([
                'still_owed',
                '',
                $row['learner_name'],
                'Current balance owed',
                $this->penceAsPoundsField((int) $row['amount_owed_pence']),
                (string) (int) $row['amount_owed_pence'],
                '',
                '',
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    private function sumRecordedIncome(string $fromUtc, string $toUtc): int
    {
        $sum = TenantContext::scopeByOrganisation(Payment::find())
            ->andWhere(['counts_as_income' => true])
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'recorded_at', $fromUtc])
            ->andWhere(['<', 'recorded_at', $toUtc])
            ->sum('amount_pence');

        return (int) ($sum ?? 0);
    }

    private function countRecordedIncome(string $fromUtc, string $toUtc): int
    {
        return (int) TenantContext::scopeByOrganisation(Payment::find())
            ->andWhere(['counts_as_income' => true])
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'recorded_at', $fromUtc])
            ->andWhere(['<', 'recorded_at', $toUtc])
            ->count();
    }

    /**
     * @return list<array{method: string, label: string, amount_pence: int, amount_label: string}>
     */
    private function paymentsReceivedBreakdown(string $fromUtc, string $toUtc): array
    {
        $rows = TenantContext::scopeByOrganisation(Payment::find())
            ->select(['method', 'SUM(amount_pence) AS total'])
            ->andWhere(['counts_as_income' => true])
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'recorded_at', $fromUtc])
            ->andWhere(['<', 'recorded_at', $toUtc])
            ->groupBy(['method'])
            ->asArray()
            ->all();

        $labels = [
            Payment::METHOD_CARD => 'Online',
            Payment::METHOD_BANK_TRANSFER => 'Bank transfer',
            Payment::METHOD_CASH => 'Cash',
        ];

        return array_map(static function (array $row) use ($labels) {
            $method = (string) $row['method'];
            $pence = (int) $row['total'];

            return [
                'method' => $method,
                'label' => $labels[$method] ?? ucfirst(str_replace('_', ' ', $method)),
                'amount_pence' => $pence,
                'amount_label' => Money::formatPence($pence),
            ];
        }, $rows);
    }

    private function sumSpending(string $fromDay, string $toDayInclusive): int
    {
        $sum = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'spent_on', $fromDay])
            ->andWhere(['<=', 'spent_on', $toDayInclusive])
            ->sum('amount_pence');

        return (int) ($sum ?? 0);
    }

    private function countSpending(string $fromDay, string $toDayInclusive): int
    {
        return (int) TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'spent_on', $fromDay])
            ->andWhere(['<=', 'spent_on', $toDayInclusive])
            ->count();
    }

    private function sumOutstanding(): int
    {
        /** @var LessonCharge[] $charges */
        $charges = TenantContext::scopeByOrganisation(LessonCharge::find())
            ->andWhere(['status' => LessonCharge::STATUS_OUTSTANDING])
            ->all();

        $total = 0;
        foreach ($charges as $charge) {
            $total += $charge->outstandingPence();
        }

        return $total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function whoOwes(int $limit): array
    {
        /** @var LessonCharge[] $charges */
        $charges = TenantContext::scopeByOrganisation(LessonCharge::find())
            ->andWhere(['status' => LessonCharge::STATUS_OUTSTANDING])
            ->all();

        $byLearner = [];
        foreach ($charges as $charge) {
            $lid = (int) $charge->learner_id;
            $byLearner[$lid] = ($byLearner[$lid] ?? 0) + $charge->outstandingPence();
        }
        arsort($byLearner);

        $rows = [];
        $i = 0;
        foreach ($byLearner as $learnerId => $pence) {
            if ($pence <= 0) {
                continue;
            }
            /** @var Learner|null $learner */
            $learner = TenantContext::scopeByOrganisation(Learner::find())
                ->andWhere(['id' => $learnerId])
                ->one();
            $rows[] = [
                'learner_id' => $learnerId,
                'learner_name' => $learner?->fullName ?? ('Pupil #' . $learnerId),
                'amount_owed_pence' => $pence,
                'amount_owed_label' => Money::formatPence($pence),
                'cta_path' => '/pupils/' . $learnerId . '?pay=1',
            ];
            $i++;
            if ($i >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentIncome(string $fromUtc, string $toUtc, int $limit): array
    {
        $org = $this->requireOrganisation();
        /** @var Payment[] $payments */
        $payments = TenantContext::scopeByOrganisation(Payment::find())
            ->andWhere(['counts_as_income' => true])
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'recorded_at', $fromUtc])
            ->andWhere(['<', 'recorded_at', $toUtc])
            ->with(['learner'])
            ->orderBy(['recorded_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();

        $rows = [];
        foreach ($payments as $payment) {
            $local = OrganisationTime::utcToLocal($payment->recorded_at, $org);
            $rows[] = [
                'id' => (int) $payment->id,
                'learner_id' => (int) $payment->learner_id,
                'learner_name' => $payment->learner?->fullName,
                'amount_pence' => (int) $payment->amount_pence,
                'amount_label' => Money::formatPence((int) $payment->amount_pence),
                'method' => $payment->method,
                'purpose' => $payment->purpose,
                'recorded_at_display' => $local->format('D j M · H:i'),
                'notes' => $payment->notes,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listExpenses(?string $fromDay = null, ?string $toDayInclusive = null): array
    {
        $query = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['voided_at' => null])
            ->orderBy(['spent_on' => SORT_DESC, 'id' => SORT_DESC]);

        if ($fromDay !== null) {
            $query->andWhere(['>=', 'spent_on', $fromDay]);
        }
        if ($toDayInclusive !== null) {
            $query->andWhere(['<=', 'spent_on', $toDayInclusive]);
        }

        return array_map(
            fn (Expense $e) => $this->serializeExpense($e),
            $query->limit(100)->all(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function spendingByCategory(string $fromDay, string $toDayInclusive): array
    {
        $rows = [];
        foreach (Expense::categories() as $category) {
            $sum = TenantContext::scopeByOrganisation(Expense::find())
                ->andWhere([
                    'voided_at' => null,
                    'category' => $category,
                ])
                ->andWhere(['>=', 'spent_on', $fromDay])
                ->andWhere(['<=', 'spent_on', $toDayInclusive])
                ->sum('amount_pence');
            $pence = (int) ($sum ?? 0);
            if ($pence <= 0) {
                continue;
            }
            $rows[] = [
                'category' => $category,
                'label' => Expense::categoryLabel($category),
                'amount_pence' => $pence,
                'amount_label' => Money::formatPence($pence),
            ];
        }

        usort($rows, static fn (array $a, array $b) => $b['amount_pence'] <=> $a['amount_pence']);

        return $rows;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        $options = [];
        foreach (Expense::categories() as $category) {
            $options[] = [
                'value' => $category,
                'label' => Expense::categoryLabel($category),
            ];
        }

        return $options;
    }

    /**
     * @return list<array{id: string, label: string, from: string, to: string}>
     */
    private function presetOptions(Organisation $org): array
    {
        $tz = OrganisationTime::timezoneFor($org);
        $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
        $monthStart = $today->modify('first day of this month');
        $lastMonthStart = $monthStart->modify('-1 month');
        $lastMonthEnd = $monthStart->modify('-1 day');

        // UK tax year starts 6 April
        $year = (int) $today->format('Y');
        $taxStartThis = new DateTimeImmutable($year . '-04-06', $tz);
        if ($today < $taxStartThis) {
            $taxStart = new DateTimeImmutable(($year - 1) . '-04-06', $tz);
            $taxEnd = new DateTimeImmutable($year . '-04-05', $tz);
        } else {
            $taxStart = $taxStartThis;
            $taxEnd = new DateTimeImmutable(($year + 1) . '-04-05', $tz);
        }

        return [
            [
                'id' => 'this_month',
                'label' => 'This month',
                'from' => $monthStart->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
            [
                'id' => 'this_month_full',
                'label' => 'This month (full)',
                'from' => $monthStart->format('Y-m-d'),
                'to' => $monthStart->modify('last day of this month')->format('Y-m-d'),
            ],
            [
                'id' => 'last_month',
                'label' => 'Last month',
                'from' => $lastMonthStart->format('Y-m-d'),
                'to' => $lastMonthEnd->format('Y-m-d'),
            ],
            [
                'id' => 'tax_year',
                'label' => 'This tax year',
                'from' => $taxStart->format('Y-m-d'),
                'to' => min($taxEnd->format('Y-m-d'), $today->format('Y-m-d')),
            ],
        ];
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string, 3: string}
     */
    private function resolveRange(Organisation $org, ?string $fromDate, ?string $toDate): array
    {
        $tz = OrganisationTime::timezoneFor($org);
        $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);

        if ($fromDate === null || trim($fromDate) === '') {
            $fromLocal = $today->modify('first day of this month');
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

        $toExclusive = $toInclusive->modify('+1 day');

        return [
            $fromLocal,
            $toExclusive,
            $fromLocal->format('j M Y'),
            $toInclusive->format('j M Y'),
        ];
    }

    private function parseDay(string $ymd, DateTimeZone $tz): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, $tz);
        $errors = DateTimeImmutable::getLastErrors();
        $bad = is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if ($dt === false || $bad) {
            throw new BadRequestHttpException('Dates must be YYYY-MM-DD.');
        }

        return $dt->setTime(0, 0, 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveAmountPence(array $data): int
    {
        if (array_key_exists('amount_pence', $data) && $data['amount_pence'] !== null && $data['amount_pence'] !== '') {
            try {
                $pence = Money::requireNonNegativePence($data['amount_pence'], 'Amount');
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        } elseif (array_key_exists('amount', $data) && $data['amount'] !== null && $data['amount'] !== '') {
            try {
                $pence = Money::poundsToPence($data['amount']);
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        } else {
            throw new BadRequestHttpException('amount_pence or amount is required.');
        }

        if ($pence <= 0) {
            throw new BadRequestHttpException('Amount must be greater than zero.');
        }

        return $pence;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeExpense(Expense $expense): array
    {
        return [
            'id' => (int) $expense->id,
            'amount_pence' => (int) $expense->amount_pence,
            'amount_label' => Money::formatPence((int) $expense->amount_pence),
            'category' => $expense->category,
            'category_label' => Expense::categoryLabel($expense->category),
            'supplier' => $expense->supplier,
            'payment_method' => $expense->payment_method,
            'vehicle_id' => $expense->vehicle_id !== null ? (int) $expense->vehicle_id : null,
            'has_receipt' => $expense->receipt_path !== null,
            'receipt_original_name' => $expense->receipt_original_name,
            'spent_on' => $expense->spent_on,
            'spent_on_display' => (new DateTimeImmutable($expense->spent_on))->format('D j M Y'),
            'notes' => $expense->notes,
            'voided_at' => $expense->voided_at,
            'void_reason' => $expense->void_reason,
        ];
    }

    public function exportPaymentsCsv(?string $fromDate = null, ?string $toDate = null): string
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toLocalExclusive] = $this->resolveRange($org, $fromDate, $toDate);
        $fromUtc = $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = $toLocalExclusive->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $lines = [$this->csvRow([
            'payment_id', 'date', 'learner_id', 'learner_name', 'amount_pounds', 'amount_pence',
            'method', 'purpose', 'counts_as_income', 'notes',
        ])];

        /** @var Payment[] $payments */
        $payments = TenantContext::scopeByOrganisation(Payment::find())
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'recorded_at', $fromUtc])
            ->andWhere(['<', 'recorded_at', $toUtc])
            ->with(['learner'])
            ->orderBy(['recorded_at' => SORT_ASC])
            ->all();

        foreach ($payments as $payment) {
            $local = OrganisationTime::utcToLocal($payment->recorded_at, $org);
            $lines[] = $this->csvRow([
                (string) (int) $payment->id,
                $local->format('Y-m-d'),
                (string) (int) $payment->learner_id,
                $payment->learner?->fullName ?? '',
                $this->penceAsPoundsField((int) $payment->amount_pence),
                (string) (int) $payment->amount_pence,
                $payment->method,
                $payment->purpose,
                $payment->counts_as_income ? 'yes' : 'no',
                (string) ($payment->notes ?? ''),
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    public function exportExpensesCsv(?string $fromDate = null, ?string $toDate = null): string
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toLocalExclusive] = $this->resolveRange($org, $fromDate, $toDate);
        $fromDay = $fromLocal->format('Y-m-d');
        $toDayInclusive = $toLocalExclusive->modify('-1 day')->format('Y-m-d');

        $lines = [$this->csvRow([
            'expense_id', 'date', 'category', 'supplier', 'amount_pounds', 'amount_pence',
            'vehicle_id', 'payment_method', 'notes', 'has_receipt',
        ])];

        /** @var Expense[] $expenses */
        $expenses = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['voided_at' => null])
            ->andWhere(['>=', 'spent_on', $fromDay])
            ->andWhere(['<=', 'spent_on', $toDayInclusive])
            ->orderBy(['spent_on' => SORT_ASC])
            ->all();

        foreach ($expenses as $expense) {
            $lines[] = $this->csvRow([
                (string) (int) $expense->id,
                $expense->spent_on,
                Expense::categoryLabel($expense->category),
                (string) ($expense->supplier ?? ''),
                $this->penceAsPoundsField((int) $expense->amount_pence),
                (string) (int) $expense->amount_pence,
                $expense->vehicle_id !== null ? (string) (int) $expense->vehicle_id : '',
                (string) ($expense->payment_method ?? ''),
                (string) ($expense->notes ?? ''),
                $expense->receipt_path ? 'yes' : 'no',
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    public function exportMileageCsv(?string $fromDate = null, ?string $toDate = null): string
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toLocalExclusive] = $this->resolveRange($org, $fromDate, $toDate);
        $fromDay = $fromLocal->format('Y-m-d');
        $toDayInclusive = $toLocalExclusive->modify('-1 day')->format('Y-m-d');

        $lines = [$this->csvRow([
            'mileage_id', 'date', 'vehicle_id', 'distance_miles', 'purpose', 'start_reading',
            'end_reading', 'notes',
        ])];

        /** @var MileageLog[] $logs */
        $logs = TenantContext::scopeByOrganisation(MileageLog::find())
            ->andWhere(['>=', 'logged_on', $fromDay])
            ->andWhere(['<=', 'logged_on', $toDayInclusive])
            ->orderBy(['logged_on' => SORT_ASC])
            ->all();

        foreach ($logs as $log) {
            $lines[] = $this->csvRow([
                (string) (int) $log->id,
                $log->logged_on,
                (string) (int) $log->vehicle_id,
                (string) $log->distance_miles,
                $log->purpose,
                $log->start_reading !== null ? (string) (int) $log->start_reading : '',
                $log->end_reading !== null ? (string) (int) $log->end_reading : '',
                (string) ($log->notes ?? ''),
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    public function exportLessonsCsv(?string $fromDate = null, ?string $toDate = null): string
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toLocalExclusive] = $this->resolveRange($org, $fromDate, $toDate);
        $fromUtc = $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = $toLocalExclusive->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $lines = [$this->csvRow([
            'lesson_id', 'date', 'time', 'learner_id', 'learner_name', 'status', 'duration_minutes',
            'price_pounds', 'price_pence', 'settlement',
        ])];

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['>=', 'starts_at', $fromUtc])
            ->andWhere(['<', 'starts_at', $toUtc])
            ->with(['learner'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        foreach ($lessons as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $price = $lesson->price_pence !== null ? (int) $lesson->price_pence : 0;
            $lines[] = $this->csvRow([
                (string) (int) $lesson->id,
                $local->format('Y-m-d'),
                $local->format('H:i'),
                (string) (int) $lesson->learner_id,
                $lesson->learner?->fullName ?? '',
                $lesson->status,
                (string) (int) $lesson->duration_minutes,
                $this->penceAsPoundsField($price),
                (string) $price,
                (string) ($lesson->settlement ?? ''),
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    public function exportTeachingIncomeCsv(?string $fromDate = null, ?string $toDate = null): string
    {
        $org = $this->requireOrganisation();
        [$fromLocal, $toLocalExclusive] = $this->resolveRange($org, $fromDate, $toDate);
        $fromUtc = $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = $toLocalExclusive->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $lines = [$this->csvRow([
            'lesson_id', 'date', 'learner_name', 'status', 'duration_minutes',
            'teaching_value_pounds', 'teaching_value_pence', 'settlement',
        ])];

        /** @var Lesson[] $completed */
        $completed = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_COMPLETED])
            ->andWhere(['not', ['settlement' => FinanceService::SETTLEMENT_WAIVED]])
            ->andWhere(['>=', 'completed_at', $fromUtc])
            ->andWhere(['<', 'completed_at', $toUtc])
            ->with(['learner'])
            ->orderBy(['completed_at' => SORT_ASC])
            ->all();

        foreach ($completed as $lesson) {
            $local = OrganisationTime::utcToLocal((string) $lesson->completed_at, $org);
            $value = TeachingValueResolver::effectiveValuePence($lesson, $org);
            $lines[] = $this->csvRow([
                (string) (int) $lesson->id,
                $local->format('Y-m-d'),
                $lesson->learner?->fullName ?? '',
                $lesson->status,
                (string) (int) $lesson->duration_minutes,
                $this->penceAsPoundsField($value),
                (string) $value,
                (string) ($lesson->settlement ?? ''),
            ]);
        }

        /** @var Lesson[] $noShows */
        $noShows = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_NO_SHOW])
            ->andWhere(['not', ['settlement' => FinanceService::SETTLEMENT_WAIVED]])
            ->andWhere(['>=', 'no_show_at', $fromUtc])
            ->andWhere(['<', 'no_show_at', $toUtc])
            ->with(['learner'])
            ->orderBy(['no_show_at' => SORT_ASC])
            ->all();

        foreach ($noShows as $lesson) {
            $local = OrganisationTime::utcToLocal((string) $lesson->no_show_at, $org);
            $value = TeachingValueResolver::effectiveValuePence($lesson, $org);
            $lines[] = $this->csvRow([
                (string) (int) $lesson->id,
                $local->format('Y-m-d'),
                $lesson->learner?->fullName ?? '',
                $lesson->status,
                '0',
                $this->penceAsPoundsField($value),
                (string) $value,
                (string) ($lesson->settlement ?? ''),
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function vehicleOptions(): array
    {
        /** @var Vehicle[] $vehicles */
        $vehicles = TenantContext::scopeByOrganisation(Vehicle::find())
            ->andWhere(['is_active' => true])
            ->orderBy(['is_primary' => SORT_DESC, 'id' => SORT_ASC])
            ->all();

        return array_map(static fn (Vehicle $v) => [
            'value' => (int) $v->id,
            'label' => $v->displayName() . ' · ' . $v->registration,
        ], $vehicles);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function paymentMethodOptions(): array
    {
        return [
            ['value' => 'card', 'label' => 'Card'],
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank_transfer', 'label' => 'Bank transfer'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }

    private function assertVehicleBelongsToOrg(int $vehicleId): void
    {
        $exists = TenantContext::scopeByOrganisation(Vehicle::find())
            ->andWhere(['id' => $vehicleId])
            ->exists();
        if (!$exists) {
            throw new BadRequestHttpException('Vehicle not found.');
        }
    }

    private function penceAsPoundsField(int $pence): string
    {
        $sign = $pence < 0 ? '-' : '';
        $abs = abs($pence);

        return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<string|int|null> $cells
     */
    private function csvRow(array $cells): string
    {
        $out = [];
        foreach ($cells as $cell) {
            $value = (string) ($cell ?? '');
            if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $out[] = $value;
        }

        return implode(',', $out);
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors !== [] ? (string) reset($errors) : 'Could not save.';
    }

    /**
     * @throws UnauthorizedHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }
}
