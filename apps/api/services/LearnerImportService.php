<?php

declare(strict_types=1);

namespace app\services;

use app\components\TenantContext;
use app\models\Learner;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UploadedFile;

/**
 * CSV pupil import — OwnLane fields only, org-scoped, preview then confirm.
 */
class LearnerImportService
{
    public const MAX_ROWS = 200;
    public const MAX_BYTES = 1_000_000;

    /** Canonical columns OwnLane imports today. */
    public const COLUMNS = [
        'first_name',
        'last_name',
        'mobile',
        'email',
        'default_pickup_address',
        'private_notes',
    ];

    /**
     * Downloadable example CSV (UTF-8 with BOM for Excel).
     */
    public function templateCsv(): string
    {
        $lines = [
            $this->templateHeaderLine(),
            $this->csvLine([
                'Aisha',
                'Khan',
                '07700 900123',
                'aisha@example.com',
                '12 High Street, Leeds LS1 1AA',
                'Prefers evenings',
            ]),
            $this->csvLine([
                'James',
                'O\'Neill',
                '07700900456',
                '',
                'Outside the college gates',
                '',
            ]),
        ];

        return "\xEF\xBB\xBF" . implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function previewFromUpload(?UploadedFile $file): array
    {
        TenantContext::requireOrganisationId();
        $rows = $this->parseUpload($file);

        return $this->buildPreview($rows);
    }

    /**
     * Confirm import — re-validates every row server-side; never trusts client status.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function confirm(array $body): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $rawRows = $body['rows'] ?? null;
        if (!is_array($rawRows)) {
            throw new BadRequestHttpException('Send the rows you want to import.');
        }
        if (count($rawRows) > self::MAX_ROWS) {
            throw new BadRequestHttpException('You can import up to ' . self::MAX_ROWS . ' pupils at a time.');
        }
        if ($rawRows === []) {
            throw new BadRequestHttpException('Nothing to import. Fix errors in your CSV and try again.');
        }

        $includeDuplicates = !empty($body['include_duplicates']);
        $learners = new LearnerService();
        $imported = [];
        $skipped = [];
        $failed = [];
        $existing = $this->existingIndex();

        $tx = Yii::$app->db->beginTransaction();
        try {
            // Recompute duplicates against org + within this batch as we go.
            $seenMobiles = [];
            $seenEmails = [];

            foreach (array_values($rawRows) as $index => $raw) {
                if (!is_array($raw)) {
                    $failed[] = [
                        'row_number' => $index + 1,
                        'errors' => ['That row could not be read.'],
                    ];
                    continue;
                }

                $rowNumber = (int) ($raw['row_number'] ?? ($index + 1));
                $parsed = $this->normaliseRowData($raw['data'] ?? $raw);
                $errors = $this->validateRow($parsed);
                if ($errors !== []) {
                    $failed[] = [
                        'row_number' => $rowNumber,
                        'errors' => $errors,
                        'data' => $parsed,
                    ];
                    continue;
                }

                $mobileKey = $this->mobileKey($parsed['mobile']);
                $emailKey = $parsed['email'] !== null ? mb_strtolower($parsed['email']) : null;

                $dup = $this->findDuplicate($parsed, $seenMobiles, $seenEmails, $existing);
                if ($dup !== null) {
                    $force = $includeDuplicates || !empty($raw['import_despite_duplicate']);
                    if (!$force) {
                        $skipped[] = [
                            'row_number' => $rowNumber,
                            'reason' => 'duplicate',
                            'duplicate' => $dup,
                            'data' => $parsed,
                        ];
                        continue;
                    }
                }

                try {
                    $created = $learners->create($parsed);
                    $imported[] = [
                        'row_number' => $rowNumber,
                        'learner_id' => $created['id'],
                        'full_name' => $created['full_name'],
                    ];
                    $seenMobiles[$mobileKey] = $rowNumber;
                    if ($emailKey !== null) {
                        $seenEmails[$emailKey] = $rowNumber;
                    }
                    // Keep org index fresh within the same import.
                    $existing['mobiles'][$mobileKey] = [
                        'id' => (int) $created['id'],
                        'name' => (string) $created['full_name'],
                    ];
                    if ($emailKey !== null) {
                        $existing['emails'][$emailKey] = [
                            'id' => (int) $created['id'],
                            'name' => (string) $created['full_name'],
                        ];
                    }
                } catch (BadRequestHttpException $e) {
                    $failed[] = [
                        'row_number' => $rowNumber,
                        'errors' => [$e->getMessage()],
                        'data' => $parsed,
                    ];
                }
            }

            if ($failed !== [] && $imported === []) {
                $tx->rollBack();
                throw new BadRequestHttpException(
                    'No pupils were imported. Check the row errors and try again.',
                );
            }

            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }

        return [
            'organisation_id' => $orgId,
            'imported_count' => count($imported),
            'skipped_count' => count($skipped),
            'failed_count' => count($failed),
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => $this->confirmMessage(count($imported), count($skipped), count($failed)),
        ];
    }

    /**
     * @param list<array<string, string>> $rawRows
     * @return array<string, mixed>
     */
    private function buildPreview(array $rawRows): array
    {
        $rows = [];
        $seenMobiles = [];
        $seenEmails = [];
        $existing = $this->existingIndex();
        $ready = 0;
        $errors = 0;
        $duplicates = 0;

        foreach ($rawRows as $i => $raw) {
            $rowNumber = $i + 2; // header is row 1
            $data = $this->normaliseRowData($raw);
            $rowErrors = $this->validateRow($data);

            $duplicate = null;
            $status = 'ready';

            if ($rowErrors !== []) {
                $status = 'error';
                $errors++;
            } else {
                $duplicate = $this->findDuplicate($data, $seenMobiles, $seenEmails, $existing);
                if ($duplicate !== null) {
                    $status = 'duplicate';
                    $duplicates++;
                } else {
                    $ready++;
                }
                $mobileKey = $this->mobileKey($data['mobile']);
                $seenMobiles[$mobileKey] = $rowNumber;
                if ($data['email'] !== null) {
                    $seenEmails[mb_strtolower($data['email'])] = $rowNumber;
                }
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'status' => $status,
                'errors' => $rowErrors,
                'duplicate' => $duplicate,
                'data' => $data,
                'display_name' => trim($data['first_name'] . ' ' . $data['last_name']),
            ];
        }

        return [
            'columns' => self::COLUMNS,
            'column_labels' => [
                'first_name' => 'First name',
                'last_name' => 'Last name',
                'mobile' => 'Mobile',
                'email' => 'Email',
                'default_pickup_address' => 'Usual pickup',
                'private_notes' => 'Private notes',
            ],
            'summary' => [
                'total' => count($rows),
                'ready' => $ready,
                'errors' => $errors,
                'duplicates' => $duplicates,
                'can_import' => $ready > 0 || $duplicates > 0,
            ],
            'rows' => $rows,
            'help' => 'Rows with errors will not import. Duplicates are skipped unless you choose to import them.',
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseUpload(?UploadedFile $file): array
    {
        if ($file === null) {
            throw new BadRequestHttpException('Choose a CSV file to upload.');
        }
        if ($file->error !== UPLOAD_ERR_OK) {
            throw new BadRequestHttpException('The file did not upload correctly. Try again.');
        }
        if ($file->size > self::MAX_BYTES) {
            throw new BadRequestHttpException('That file is too large. Keep it under 1 MB.');
        }

        $ext = mb_strtolower((string) $file->extension);
        $name = mb_strtolower((string) $file->name);
        if ($ext !== 'csv' && !str_ends_with($name, '.csv')) {
            throw new BadRequestHttpException('Upload a .csv file (you can save from Excel as CSV).');
        }

        $path = $file->tempName;
        if ($path === '' || !is_readable($path)) {
            throw new BadRequestHttpException('Could not read the uploaded file.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new BadRequestHttpException('Could not read the uploaded file.');
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null] || $header === []) {
                throw new BadRequestHttpException('Your CSV looks empty. Download the example and try again.');
            }
            $header[0] = $this->stripBom((string) ($header[0] ?? ''));
            $map = $this->mapHeaders($header);

            $rows = [];
            while (($cells = fgetcsv($handle)) !== false) {
                if ($this->isEmptyCsvRow($cells)) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw new BadRequestHttpException(
                        'This file has more than ' . self::MAX_ROWS . ' pupils. Split it into smaller files.',
                    );
                }
                $assoc = [];
                foreach ($map as $canonical => $index) {
                    $assoc[$canonical] = isset($cells[$index]) ? trim((string) $cells[$index]) : '';
                }
                $rows[] = $assoc;
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw new BadRequestHttpException('No pupil rows found under the header. Add at least one pupil.');
        }

        return $rows;
    }

    /**
     * @param list<string|null> $header
     * @return array<string, int>
     */
    private function mapHeaders(array $header): array
    {
        $aliases = [
            'first_name' => ['first_name', 'firstname', 'first name', 'given name', 'forename'],
            'last_name' => ['last_name', 'lastname', 'last name', 'surname', 'family name'],
            'mobile' => ['mobile', 'mobile number', 'mobile_number', 'phone', 'phone number', 'telephone', 'tel'],
            'email' => ['email', 'e-mail', 'email address', 'e mail'],
            'default_pickup_address' => [
                'default_pickup_address',
                'usual pickup',
                'pickup',
                'pickup address',
                'pickup_address',
                'address',
                'home address',
            ],
            'private_notes' => ['private_notes', 'private notes', 'notes', 'instructor notes'],
        ];

        $map = [];
        foreach ($header as $index => $label) {
            $key = $this->headerKey((string) $label);
            if ($key === '') {
                continue;
            }
            foreach ($aliases as $canonical => $names) {
                if (in_array($key, $names, true) && !isset($map[$canonical])) {
                    $map[$canonical] = (int) $index;
                    break;
                }
            }
        }

        $missing = [];
        foreach (['first_name', 'last_name', 'mobile'] as $required) {
            if (!isset($map[$required])) {
                $missing[] = $required === 'first_name' ? 'First name'
                    : ($required === 'last_name' ? 'Last name' : 'Mobile');
            }
        }
        if ($missing !== []) {
            throw new BadRequestHttpException(
                'Your CSV needs these columns: ' . implode(', ', $missing)
                . '. Download the example format to match.',
            );
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   first_name: string,
     *   last_name: string,
     *   mobile: string,
     *   email: string|null,
     *   default_pickup_address: string|null,
     *   private_notes: string|null
     * }
     */
    private function normaliseRowData(array $raw): array
    {
        $email = trim((string) ($raw['email'] ?? ''));
        $pickup = trim((string) ($raw['default_pickup_address'] ?? ''));
        $notes = trim((string) ($raw['private_notes'] ?? ''));

        return [
            'first_name' => trim((string) ($raw['first_name'] ?? '')),
            'last_name' => trim((string) ($raw['last_name'] ?? '')),
            'mobile' => trim((string) ($raw['mobile'] ?? '')),
            'email' => $email === '' ? null : mb_strtolower($email),
            'default_pickup_address' => $pickup === '' ? null : $pickup,
            'private_notes' => $notes === '' ? null : $notes,
        ];
    }

    /**
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   mobile: string,
     *   email: string|null,
     *   default_pickup_address: string|null,
     *   private_notes: string|null
     * } $data
     * @return list<string>
     */
    private function validateRow(array $data): array
    {
        $errors = [];
        if ($data['first_name'] === '') {
            $errors[] = 'First name is missing.';
        } elseif (mb_strlen($data['first_name']) > 100) {
            $errors[] = 'First name is too long.';
        }
        if ($data['last_name'] === '') {
            $errors[] = 'Last name is missing.';
        } elseif (mb_strlen($data['last_name']) > 100) {
            $errors[] = 'Last name is too long.';
        }
        if ($data['mobile'] === '') {
            $errors[] = 'Mobile number is missing.';
        } else {
            $digits = preg_replace('/\D/', '', $data['mobile']) ?? '';
            if (strlen($digits) < 10) {
                $errors[] = 'Mobile number looks too short.';
            } elseif (mb_strlen($data['mobile']) > 32) {
                $errors[] = 'Mobile number is too long.';
            }
        }
        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address looks invalid.';
        }

        return $errors;
    }

    /**
     * @param array{first_name: string, last_name: string, mobile: string, email: string|null, ...} $data
     * @param array<string, int> $seenMobiles
     * @param array<string, int> $seenEmails
     * @param array{mobiles: array<string, array{id: int, name: string}>, emails: array<string, array{id: int, name: string}>} $existing
     * @return array<string, mixed>|null
     */
    private function findDuplicate(array $data, array $seenMobiles, array $seenEmails, array $existing): ?array
    {
        $mobileKey = $this->mobileKey($data['mobile']);
        if ($mobileKey !== '' && isset($seenMobiles[$mobileKey])) {
            return [
                'match' => 'mobile',
                'source' => 'file',
                'other_row_number' => $seenMobiles[$mobileKey],
                'message' => 'Same mobile as row ' . $seenMobiles[$mobileKey] . ' in this file.',
            ];
        }
        if ($data['email'] !== null) {
            $emailKey = mb_strtolower($data['email']);
            if (isset($seenEmails[$emailKey])) {
                return [
                    'match' => 'email',
                    'source' => 'file',
                    'other_row_number' => $seenEmails[$emailKey],
                    'message' => 'Same email as row ' . $seenEmails[$emailKey] . ' in this file.',
                ];
            }
        }

        if ($mobileKey !== '' && isset($existing['mobiles'][$mobileKey])) {
            $hit = $existing['mobiles'][$mobileKey];

            return [
                'match' => 'mobile',
                'source' => 'existing',
                'existing_learner_id' => $hit['id'],
                'existing_name' => $hit['name'],
                'message' => 'Already in OwnLane as ' . $hit['name'] . ' (same mobile).',
            ];
        }

        if ($data['email'] !== null) {
            $emailKey = mb_strtolower($data['email']);
            if (isset($existing['emails'][$emailKey])) {
                $hit = $existing['emails'][$emailKey];

                return [
                    'match' => 'email',
                    'source' => 'existing',
                    'existing_learner_id' => $hit['id'],
                    'existing_name' => $hit['name'],
                    'message' => 'Already in OwnLane as ' . $hit['name'] . ' (same email).',
                ];
            }
        }

        return null;
    }

    /**
     * @return array{mobiles: array<string, array{id: int, name: string}>, emails: array<string, array{id: int, name: string}>}
     */
    private function existingIndex(): array
    {
        /** @var Learner[] $learners */
        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->all();

        $mobiles = [];
        $emails = [];
        foreach ($learners as $learner) {
            $mKey = $this->mobileKey((string) $learner->mobile);
            if ($mKey !== '' && !isset($mobiles[$mKey])) {
                $mobiles[$mKey] = [
                    'id' => (int) $learner->id,
                    'name' => $learner->fullName,
                ];
            }
            $email = trim((string) ($learner->email ?? ''));
            if ($email !== '') {
                $eKey = mb_strtolower($email);
                if (!isset($emails[$eKey])) {
                    $emails[$eKey] = [
                        'id' => (int) $learner->id,
                        'name' => $learner->fullName,
                    ];
                }
            }
        }

        return ['mobiles' => $mobiles, 'emails' => $emails];
    }

    private function mobileKey(string $mobile): string
    {
        return preg_replace('/\D/', '', $mobile) ?? '';
    }

    private function headerKey(string $label): string
    {
        $label = $this->stripBom($label);
        $label = mb_strtolower(trim($label));
        $label = str_replace(['_', '-'], ' ', $label);
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;

        return $label;
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /**
     * @param list<string|null> $cells
     */
    private function isEmptyCsvRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function templateHeaderLine(): string
    {
        return $this->csvLine([
            'First name',
            'Last name',
            'Mobile',
            'Email',
            'Usual pickup',
            'Private notes',
        ]);
    }

    /**
     * @param list<string> $fields
     */
    private function csvLine(array $fields): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return implode(',', $fields);
        }
        fputcsv($out, $fields);
        rewind($out);
        $line = stream_get_contents($out);
        fclose($out);

        return rtrim((string) $line, "\r\n");
    }

    private function confirmMessage(int $imported, int $skipped, int $failed): string
    {
        $parts = [];
        if ($imported === 1) {
            $parts[] = '1 pupil imported';
        } else {
            $parts[] = $imported . ' pupils imported';
        }
        if ($skipped > 0) {
            $parts[] = $skipped . ' skipped';
        }
        if ($failed > 0) {
            $parts[] = $failed . ' failed';
        }

        return implode(' · ', $parts) . '.';
    }
}
