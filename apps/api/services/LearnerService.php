<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerContact;
use app\models\LearnerLocation;
use app\models\LearnerServiceRate;
use app\models\Organisation;
use app\models\OrganisationService;
use DateTimeImmutable;
use DateTimeZone;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Organisation-scoped pupil management.
 */
class LearnerService
{
    /**
     * Active pupils for the current organisation, optional search.
     *
     * @return list<array<string, mixed>>
     */
    public function listActive(?string $query = null): array
    {
        return $this->listByStatus(Learner::STATUS_ACTIVE, $query);
    }

    /**
     * Waiting-list pupils for the organisation (gap matching eligible foundation).
     *
     * @return list<array<string, mixed>>
     */
    public function listWaiting(): array
    {
        return $this->listByStatus(Learner::STATUS_WAITING);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByStatus(string $status, ?string $query = null): array
    {
        if (!in_array($status, Learner::listStatusValues(), true)) {
            throw new BadRequestHttpException('Unknown pupil status.');
        }

        $q = TenantContext::scopeByOrganisation(Learner::find())
            ->orderBy(['last_name' => SORT_ASC, 'first_name' => SORT_ASC]);

        if ($status === Learner::STATUS_ALL) {
            // No lifecycle / archived filter — every pupil in the organisation.
        } elseif ($status === Learner::STATUS_INACTIVE) {
            $q->andWhere(['not', ['archived_at' => null]]);
        } else {
            $q->andWhere(['archived_at' => null])
                ->andWhere(['lifecycle' => $status]);
            if ($status === Learner::STATUS_WAITING) {
                $q->orderBy(['waiting_list_joined_at' => SORT_ASC, 'last_name' => SORT_ASC, 'first_name' => SORT_ASC]);
            }
        }

        $term = trim((string) $query);
        if ($term !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)) . '%';
            $q->andWhere(
                'LOWER(first_name) LIKE :t
                 OR LOWER(COALESCE(middle_name, \'\')) LIKE :t
                 OR LOWER(last_name) LIKE :t
                 OR LOWER(CONCAT(first_name, \' \', COALESCE(middle_name || \' \', \'\'), last_name)) LIKE :t
                 OR LOWER(CONCAT(first_name, \' \', last_name)) LIKE :t
                 OR LOWER(COALESCE(email, \'\')) LIKE :t
                 OR REPLACE(mobile, \' \', \'\') LIKE :mobile',
                [
                    ':t' => $like,
                    ':mobile' => '%' . preg_replace('/\s+/', '', $term) . '%',
                ],
            );
        }

        return array_map(
            static fn (Learner $learner) => $learner->toListArray(),
            $q->all(),
        );
    }

    /**
     * Counts per instructor-facing status.
     *
     * @return array{active: int, waiting: int, paused: int, passed: int, inactive: int}
     */
    public function statusCounts(): array
    {
        $base = TenantContext::scopeByOrganisation(Learner::find());

        $active = (clone $base)->andWhere(['archived_at' => null, 'lifecycle' => Learner::LIFECYCLE_ACTIVE])->count();
        $waiting = (clone $base)->andWhere(['archived_at' => null, 'lifecycle' => Learner::LIFECYCLE_WAITING])->count();
        $paused = (clone $base)->andWhere(['archived_at' => null, 'lifecycle' => Learner::LIFECYCLE_PAUSED])->count();
        $passed = (clone $base)->andWhere(['archived_at' => null, 'lifecycle' => Learner::LIFECYCLE_PASSED])->count();
        $inactive = (clone $base)->andWhere(['not', ['archived_at' => null]])->count();

        return [
            'active' => (int) $active,
            'waiting' => (int) $waiting,
            'paused' => (int) $paused,
            'passed' => (int) $passed,
            'inactive' => (int) $inactive,
        ];
    }

    /**
     * Set instructor-facing status.
     *
     * @return array<string, mixed>
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function setStatus(int $id, string $status): array
    {
        $status = trim($status);
        if (!in_array($status, Learner::statusValues(), true)) {
            throw new BadRequestHttpException('Unknown pupil status.');
        }

        if ($status === Learner::STATUS_INACTIVE) {
            return $this->archive($id);
        }

        $learner = $this->findOwned($id);
        $now = gmdate('Y-m-d H:i:s');

        $learner->archived_at = null;
        $learner->lifecycle = $status;
        if ($status === Learner::LIFECYCLE_WAITING) {
            if ($learner->waiting_list_joined_at === null || $learner->waiting_list_joined_at === '') {
                $learner->waiting_list_joined_at = $now;
            }
        } else {
            $learner->waiting_list_joined_at = null;
        }
        $learner->updated_at = $now;

        if (!$learner->save(true, ['archived_at', 'lifecycle', 'waiting_list_joined_at', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($learner));
        }

        return $this->withTestJourney($learner);
    }

    /**
     * @throws NotFoundHttpException
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        $learner = $this->findOwned($id);
        (new LearnerProfileChangeService())->markSeenForLearner($id);
        $payload = $this->withTestJourney($learner);
        $payload['finance'] = (new FinanceService())->summaryForLearner($id);
        $payload['portal'] = (new PortalAuthService())->statusForLearner($id);
        $payload['continuity'] = (new ContinuityService())->contextForLearner($id);

        return $payload;
    }

    /**
     * Create — only require what an instructor needs to start teaching.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws BadRequestHttpException
     */
    public function create(array $data): array
    {
        $learner = new Learner();
        $learner->organisation_id = TenantContext::requireOrganisationId();
        $this->applyCreateFields($learner, $data);

        $now = gmdate('Y-m-d H:i:s');
        $learner->created_at = $now;
        $learner->updated_at = $now;

        if (!$learner->save()) {
            throw new BadRequestHttpException($this->firstError($learner));
        }

        return $this->withTestJourney($learner);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function update(int $id, array $data): array
    {
        $learner = $this->findOwned($id);
        $this->applyUpdateFields($learner, $data);
        if (array_key_exists('contacts', $data)) {
            $this->replaceContacts($learner, is_array($data['contacts']) ? $data['contacts'] : []);
        } elseif (
            array_key_exists('emergency_contact_name', $data)
            || array_key_exists('emergency_contact_phone', $data)
        ) {
            $this->syncLegacyEmergency($learner);
        }
        $learner->updated_at = gmdate('Y-m-d H:i:s');

        if (!$learner->save()) {
            throw new BadRequestHttpException($this->firstError($learner));
        }

        return $this->withTestJourney($learner);
    }

    /**
     * Soft-archive. Does not delete history OwnLane will attach later.
     *
     * @return array<string, mixed>
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function archive(int $id): array
    {
        $learner = $this->findOwned($id);
        if ($learner->isArchived) {
            throw new BadRequestHttpException('This pupil is already archived.');
        }

        $learner->archived_at = gmdate('Y-m-d H:i:s');
        $learner->updated_at = $learner->archived_at;

        if (!$learner->save(true, ['archived_at', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($learner));
        }

        return $this->withTestJourney($learner);
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findOwned(int $id): Learner
    {
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $id])
            ->one();

        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException
     */
    private function applyCreateFields(Learner $learner, array $data): void
    {
        $learner->first_name = $this->requiredString($data, 'first_name', 'First name');
        $learner->middle_name = $this->optionalText($data['middle_name'] ?? null, 100);
        $learner->last_name = $this->requiredString($data, 'last_name', 'Last name');
        $learner->mobile = $this->normaliseMobile($this->requiredString($data, 'mobile', 'Mobile number'));
        $learner->email = $this->optionalEmail($data['email'] ?? null);
        $learner->default_pickup_address = $this->optionalText($data['default_pickup_address'] ?? null);
        $learner->private_notes = $this->optionalText($data['private_notes'] ?? null);
        $learner->date_of_birth = $this->optionalDate($data['date_of_birth'] ?? null);
        $gender = trim((string) ($data['gender'] ?? ''));
        $learner->gender = in_array($gender, Learner::genderValues(), true) ? $gender : null;
        $learner->lifecycle = Learner::LIFECYCLE_ACTIVE;
        // Test fields are edit-time / intake — keep manual create fast.
        $learner->test_date = null;
        $learner->test_centre = null;
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException
     */
    private function applyUpdateFields(Learner $learner, array $data): void
    {
        if (array_key_exists('first_name', $data)) {
            $learner->first_name = $this->requiredString($data, 'first_name', 'First name');
        }
        if (array_key_exists('middle_name', $data)) {
            $learner->middle_name = $this->optionalText($data['middle_name'], 100);
        }
        if (array_key_exists('last_name', $data)) {
            $learner->last_name = $this->requiredString($data, 'last_name', 'Last name');
        }
        if (array_key_exists('mobile', $data)) {
            $learner->mobile = $this->normaliseMobile($this->requiredString($data, 'mobile', 'Mobile number'));
        }
        if (array_key_exists('email', $data)) {
            $learner->email = $this->optionalEmail($data['email']);
        }
        if (array_key_exists('default_pickup_address', $data)) {
            $learner->default_pickup_address = $this->optionalText($data['default_pickup_address']);
        }
        if (array_key_exists('test_date', $data)) {
            $learner->test_date = $this->optionalDate($data['test_date']);
        }
        if (array_key_exists('test_centre', $data)) {
            $learner->test_centre = $this->optionalText($data['test_centre'], 255);
        }
        if (array_key_exists('practical_test_time', $data)) {
            $learner->practical_test_time = $this->optionalTime($data['practical_test_time']);
        }
        if (array_key_exists('practical_test_booking_ref', $data)) {
            $learner->practical_test_booking_ref = $this->optionalText($data['practical_test_booking_ref'], 64);
        }
        if (array_key_exists('practical_test_cancel_by', $data)) {
            $learner->practical_test_cancel_by = $this->optionalDate($data['practical_test_cancel_by']);
        }
        if (array_key_exists('practical_test_reminder_offsets', $data)) {
            $learner->practical_test_reminder_offsets = $this->encodeReminderOffsets($data['practical_test_reminder_offsets']);
        }
        if (array_key_exists('theory_status', $data)
            || array_key_exists('theory_pass_date', $data)
            || array_key_exists('theory_test_date', $data)
        ) {
            $this->applyTheoryFields($learner, $data);
        }
        if (array_key_exists('licence_number', $data)) {
            $learner->licence_number = $this->optionalText($data['licence_number'], 32);
        }
        if (array_key_exists('licence_expiry_date', $data)) {
            $learner->licence_expiry_date = $this->optionalDate($data['licence_expiry_date']);
        }
        if (array_key_exists('emergency_contact_name', $data)) {
            $learner->emergency_contact_name = $this->optionalText($data['emergency_contact_name'], 120);
        }
        if (array_key_exists('emergency_contact_phone', $data)) {
            $learner->emergency_contact_phone = $this->optionalText($data['emergency_contact_phone'], 32);
        }
        if (array_key_exists('date_of_birth', $data)) {
            $learner->date_of_birth = $this->optionalDate($data['date_of_birth']);
        }
        if (array_key_exists('gender', $data)) {
            $gender = trim((string) ($data['gender'] ?? ''));
            $learner->gender = in_array($gender, Learner::genderValues(), true) ? $gender : null;
        }
        if (array_key_exists('transmission', $data)) {
            $tx = trim((string) ($data['transmission'] ?? ''));
            $learner->transmission = in_array($tx, ['manual', 'automatic', 'either'], true) ? $tx : null;
        }
        if (array_key_exists('preferred_contact', $data)) {
            $pref = trim((string) ($data['preferred_contact'] ?? ''));
            $learner->preferred_contact = in_array($pref, ['sms', 'whatsapp', 'email', 'call'], true) ? $pref : null;
        }
        if (array_key_exists('eyesight_status', $data)) {
            $status = trim((string) ($data['eyesight_status'] ?? ''));
            $learner->eyesight_status = in_array($status, Learner::eyesightValues(), true) ? $status : null;
        }
        if (array_key_exists('eyesight_checked_on', $data)) {
            $learner->eyesight_checked_on = $this->optionalDate($data['eyesight_checked_on']);
        }
        if (array_key_exists('wears_glasses', $data)) {
            $learner->wears_glasses = (bool) $data['wears_glasses'];
        }
        if (array_key_exists('medical_notes', $data)) {
            $learner->medical_notes = $this->optionalText($data['medical_notes']);
        }
        if (array_key_exists('referred_by', $data)) {
            $learner->referred_by = $this->optionalText($data['referred_by'], 120);
        }
        if (array_key_exists('payment_notes', $data)) {
            $learner->payment_notes = $this->optionalText($data['payment_notes']);
        }
        if (array_key_exists('available_from', $data)) {
            $learner->available_from = $this->optionalDate($data['available_from']);
        }
        if (array_key_exists('private_notes', $data)) {
            $learner->private_notes = $this->optionalText($data['private_notes']);
        }
    }

    /**
     * Keep theory status and dates consistent.
     *
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException
     */
    private function applyTheoryFields(Learner $learner, array $data): void
    {
        if (array_key_exists('theory_status', $data)) {
            $status = trim((string) ($data['theory_status'] ?? ''));
            $learner->theory_status = in_array($status, ['passed', 'not_yet', 'booked'], true) ? $status : null;
        }
        if (array_key_exists('theory_pass_date', $data)) {
            $learner->theory_pass_date = $this->optionalDate($data['theory_pass_date']);
        }
        if (array_key_exists('theory_test_date', $data)) {
            $learner->theory_test_date = $this->optionalDate($data['theory_test_date']);
        }

        // Infer status from dates when the client only sends dates.
        if (($learner->theory_status === null || $learner->theory_status === '')
            && $learner->theory_pass_date
        ) {
            $learner->theory_status = 'passed';
        } elseif (($learner->theory_status === null || $learner->theory_status === '')
            && $learner->theory_test_date
        ) {
            $learner->theory_status = 'booked';
        }

        if ($learner->theory_status === 'not_yet') {
            $learner->theory_pass_date = null;
            $learner->theory_test_date = null;
        } elseif ($learner->theory_status === 'booked') {
            $learner->theory_pass_date = null;
            if ($learner->theory_test_date === null || $learner->theory_test_date === '') {
                throw new BadRequestHttpException('Add the theory test date when it’s booked.');
            }
        } elseif ($learner->theory_status === 'passed') {
            $learner->theory_test_date = null;
            if ($learner->theory_pass_date === null || $learner->theory_pass_date === '') {
                throw new BadRequestHttpException('Add the pass date when theory is passed.');
            }
        }
    }

    /**
     * @throws BadRequestHttpException
     */
    private function optionalTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $time = trim((string) $value);
        if (preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
            throw new BadRequestHttpException('Time must be HH:MM.');
        }

        return $time;
    }

    private function encodeReminderOffsets(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_array($value)) {
            throw new BadRequestHttpException('Reminder offsets must be a list of days.');
        }
        $out = [];
        foreach ($value as $v) {
            if (is_int($v) || (is_string($v) && ctype_digit((string) $v))) {
                $day = (int) $v;
                if ($day >= 0 && $day <= 56) {
                    $out[] = $day;
                }
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out === [] ? null : (json_encode($out) ?: null);
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException
     */
    private function requiredString(array $data, string $key, string $label): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new BadRequestHttpException("{$label} is required.");
        }

        return $value;
    }

    private function optionalText(mixed $value, ?int $max = null): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if ($max !== null && mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max);
        }

        return $text;
    }

    /**
     * @throws BadRequestHttpException
     */
    private function optionalEmail(mixed $value): ?string
    {
        $email = $this->optionalText($value, 255);
        if ($email === null) {
            return null;
        }
        $email = mb_strtolower($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Enter a valid email address.');
        }

        return $email;
    }

    /**
     * @throws BadRequestHttpException
     */
    private function optionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = trim((string) $value);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dt === false || $dt->format('Y-m-d') !== $date) {
            throw new BadRequestHttpException('Test date must be YYYY-MM-DD.');
        }

        return $date;
    }

    /**
     * Keep digits, spaces, +, and leading 0 — enough for UK mobiles without a phone lib.
     *
     * @throws BadRequestHttpException
     */
    private function normaliseMobile(string $mobile): string
    {
        $mobile = trim($mobile);
        $compact = preg_replace('/[^\d+]/', '', $mobile) ?? '';
        if ($compact === '' || strlen(preg_replace('/\D/', '', $compact) ?? '') < 10) {
            throw new BadRequestHttpException('Enter a valid mobile number.');
        }

        return $mobile;
    }

    /**
     * Lean learner-count report for a date range.
     *
     * Movements use created_at / archived_at / status updates — not a full history ledger.
     *
     * @return array<string, mixed>
     */
    public function report(?string $fromDate = null, ?string $toDate = null): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new BadRequestHttpException('Organisation not found.');
        }

        [$fromLocal, $toExclusive, $fromLabel, $toLabel] = $this->resolveReportRange($org, $fromDate, $toDate);
        $toInclusive = $toExclusive->modify('-1 day');
        $fromUtc = $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = $toExclusive->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $tz = OrganisationTime::timezoneFor($org);

        $counts = $this->statusCounts();
        $learnerCount = $counts['active'];

        $newCount = (int) TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['>=', 'created_at', $fromUtc])
            ->andWhere(['<', 'created_at', $toUtc])
            ->count();

        $passedCount = (int) TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null, 'lifecycle' => Learner::LIFECYCLE_PASSED])
            ->andWhere(['>=', 'updated_at', $fromUtc])
            ->andWhere(['<', 'updated_at', $toUtc])
            ->count();

        $inactiveCount = (int) TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['not', ['archived_at' => null]])
            ->andWhere(['>=', 'archived_at', $fromUtc])
            ->andWhere(['<', 'archived_at', $toUtc])
            ->count();

        $leftCount = $passedCount + $inactiveCount;
        $startEstimate = max(0, $learnerCount - $newCount + $leftCount);
        $growthPercent = null;
        if ($startEstimate > 0) {
            $growthPercent = round((($learnerCount - $startEstimate) / $startEstimate) * 1000) / 10;
        } elseif ($learnerCount > 0 && $newCount > 0) {
            $growthPercent = 100.0;
        }

        $series = $this->buildReportSeries(
            $fromLocal,
            $toInclusive,
            $tz,
            $startEstimate,
        );

        $growthSeries = array_map(static fn (array $row): float => (float) $row['active_estimate'], $series);
        $newSeries = array_map(static fn (array $row): int => (int) $row['new_learners'], $series);

        return [
            'from' => $fromLocal->format('Y-m-d'),
            'to' => $toInclusive->format('Y-m-d'),
            'from_label' => $fromLabel,
            'to_label' => $toLabel,
            'range_label' => $fromLabel . ' – ' . $toLabel,
            'learner_count' => $learnerCount,
            'breakdown' => [
                ['key' => 'new', 'label' => 'New learners', 'count' => $newCount],
                ['key' => 'passed', 'label' => 'Passed', 'count' => $passedCount],
                ['key' => 'inactive', 'label' => 'Marked inactive', 'count' => $inactiveCount],
                ['key' => 'active', 'label' => 'Active now', 'count' => $learnerCount],
            ],
            'new_learners' => $newCount,
            'passed' => $passedCount,
            'inactive' => $inactiveCount,
            'growth_percent' => $growthPercent,
            'growth_label' => $growthPercent === null
                ? '—'
                : (($growthPercent > 0 ? '+' : '') . rtrim(rtrim(number_format($growthPercent, 1, '.', ''), '0'), '.') . '%'),
            'series' => $series,
            'growth_series' => $growthSeries,
            'new_series' => $newSeries,
            'month_labels' => array_map(static fn (array $row): string => (string) $row['label'], $series),
            'status_counts' => $counts,
            'presets' => $this->reportPresets($org),
            'hint' => 'Active now is today’s teaching list. New / passed / inactive are movements in the selected dates.',
        ];
    }

    /**
     * @return list<array{month: string, label: string, new_learners: int, passed: int, inactive: int, net: int, active_estimate: int}>
     */
    private function buildReportSeries(
        DateTimeImmutable $fromLocal,
        DateTimeImmutable $toInclusive,
        DateTimeZone $tz,
        int $startEstimate,
    ): array {
        $cursor = $fromLocal->modify('first day of this month')->setTime(0, 0, 0);
        $endMonth = $toInclusive->modify('first day of this month')->setTime(0, 0, 0);
        $months = [];
        while ($cursor <= $endMonth) {
            $key = $cursor->format('Y-m');
            $months[$key] = [
                'month' => $key,
                'label' => $cursor->format('M'),
                'new_learners' => 0,
                'passed' => 0,
                'inactive' => 0,
                'net' => 0,
                'active_estimate' => 0,
            ];
            $cursor = $cursor->modify('+1 month');
        }

        if ($months === []) {
            return [];
        }

        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->select(['created_at', 'archived_at', 'lifecycle', 'updated_at'])
            ->asArray()
            ->all();

        foreach ($learners as $row) {
            $createdKey = $this->monthKeyUtc((string) ($row['created_at'] ?? ''), $tz);
            if ($createdKey !== null && isset($months[$createdKey])) {
                $months[$createdKey]['new_learners']++;
            }

            $archived = $row['archived_at'] ?? null;
            if (is_string($archived) && $archived !== '') {
                $archivedKey = $this->monthKeyUtc($archived, $tz);
                if ($archivedKey !== null && isset($months[$archivedKey])) {
                    $months[$archivedKey]['inactive']++;
                }
            } elseif (($row['lifecycle'] ?? '') === Learner::LIFECYCLE_PASSED) {
                $passedKey = $this->monthKeyUtc((string) ($row['updated_at'] ?? ''), $tz);
                if ($passedKey !== null && isset($months[$passedKey])) {
                    $months[$passedKey]['passed']++;
                }
            }
        }

        $running = $startEstimate;
        $out = [];
        foreach ($months as $row) {
            $net = (int) $row['new_learners'] - (int) $row['passed'] - (int) $row['inactive'];
            $running = max(0, $running + $net);
            $row['net'] = $net;
            $row['active_estimate'] = $running;
            $out[] = $row;
        }

        return $out;
    }

    private function monthKeyUtc(string $utc, DateTimeZone $tz): ?string
    {
        $utc = trim($utc);
        if ($utc === '') {
            return null;
        }
        try {
            $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }

        return $dt->setTimezone($tz)->format('Y-m');
    }

    /**
     * @return list<array{id: string, label: string, from: string, to: string}>
     */
    private function reportPresets(Organisation $org): array
    {
        $tz = OrganisationTime::timezoneFor($org);
        $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
        $monthStart = $today->modify('first day of this month');
        $sixMonthsAgo = $today->modify('-5 months')->modify('first day of this month');
        $yearStart = $today->modify('first day of January this year');

        return [
            [
                'id' => 'this_month',
                'label' => 'This month',
                'from' => $monthStart->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
            [
                'id' => 'last_6_months',
                'label' => 'Last 6 months',
                'from' => $sixMonthsAgo->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
            [
                'id' => 'this_year',
                'label' => 'This year',
                'from' => $yearStart->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
        ];
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string, 3: string}
     */
    private function resolveReportRange(Organisation $org, ?string $fromDate, ?string $toDate): array
    {
        $tz = OrganisationTime::timezoneFor($org);
        $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);

        if ($fromDate === null || trim($fromDate) === '') {
            $fromLocal = $today->modify('-5 months')->modify('first day of this month');
        } else {
            $fromLocal = $this->parseReportDay(trim($fromDate), $tz);
        }

        if ($toDate === null || trim($toDate) === '') {
            $toInclusive = $today;
        } else {
            $toInclusive = $this->parseReportDay(trim($toDate), $tz);
        }

        if ($toInclusive < $fromLocal) {
            throw new BadRequestHttpException('End date must be on or after the start date.');
        }

        return [
            $fromLocal,
            $toInclusive->modify('+1 day'),
            $fromLocal->format('j M Y'),
            $toInclusive->format('j M Y'),
        ];
    }

    private function parseReportDay(string $ymd, DateTimeZone $tz): DateTimeImmutable
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
     * @return array<string, mixed>
     */
    private function withTestJourney(Learner $learner): array
    {
        $payload = $learner->toApiArray();
        $org = Organisation::findOne(['id' => (int) $learner->organisation_id]);
        if ($org !== null) {
            $payload['test_journey'] = (new TestJourneyService())->build($learner, $org);
        } else {
            $payload['test_journey'] = null;
        }
        $payload['theory'] = \app\components\TheoryCertificate::statusPayload(
            $learner->theory_status,
            $learner->theory_pass_date,
            null,
            $learner->theory_test_date,
        );
        $payload['licence'] = \app\components\LicenceStatus::statusPayload(
            $learner->licence_number,
            $learner->licence_expiry_date,
        );
        $payload['profile_changes'] = (new LearnerProfileChangeService())->recentForLearner((int) $learner->id);
        $payload['contacts'] = $this->contactsForLearner($learner);
        $payload['places'] = $this->placesForLearner($learner);
        $payload['service_rates'] = $this->ratesForLearner($learner);
        $payload['standard_rate'] = $this->standardRatePayload($org);

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contactsForLearner(Learner $learner): array
    {
        $rows = LearnerContact::find()
            ->andWhere([
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
            ])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(
            static fn (LearnerContact $c) => $c->toApiArray(),
            $rows,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placesForLearner(Learner $learner): array
    {
        $rows = LearnerLocation::find()
            ->andWhere([
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
            ])
            ->orderBy(['is_default' => SORT_DESC, 'sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(
            static fn (LearnerLocation $row) => (new LearnerLocationService())->toArray($row),
            $rows,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ratesForLearner(Learner $learner): array
    {
        $today = gmdate('Y-m-d');
        $rows = LearnerServiceRate::find()
            ->andWhere([
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
            ])
            ->orderBy(['effective_from' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        $out = [];
        foreach ($rows as $rate) {
            $service = $rate->service_id !== null
                ? OrganisationService::findOne((int) $rate->service_id)
                : null;
            $active = $rate->effective_from <= $today
                && ($rate->effective_to === null || $rate->effective_to >= $today);
            $out[] = [
                'id' => (int) $rate->id,
                'service_id' => $rate->service_id !== null ? (int) $rate->service_id : null,
                'service_name' => $service?->name ?? 'All lessons',
                'price_pence' => (int) $rate->price_pence,
                'price_label' => Money::formatPence((int) $rate->price_pence),
                'effective_from' => (string) $rate->effective_from,
                'effective_to' => $rate->effective_to,
                'note' => $rate->note,
                'is_active' => $active,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function standardRatePayload(?Organisation $org): ?array
    {
        if ($org === null) {
            return null;
        }
        $pence = (int) ($org->default_hourly_rate_pence ?: Organisation::DEFAULT_HOURLY_RATE_PENCE);

        return [
            'price_pence' => $pence,
            'price_label' => Money::formatPence($pence),
            'label' => 'Your usual hourly rate',
        ];
    }

    /**
     * Replace all contacts for a learner and keep legacy emergency columns in sync.
     *
     * @param list<mixed> $rows
     * @throws BadRequestHttpException
     */
    private function replaceContacts(Learner $learner, array $rows): void
    {
        LearnerContact::deleteAll([
            'organisation_id' => (int) $learner->organisation_id,
            'learner_id' => (int) $learner->id,
        ]);

        $now = gmdate('Y-m-d H:i:s');
        $order = 0;
        $firstEmergency = null;

        foreach ($rows as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $name = trim((string) ($raw['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $kind = trim((string) ($raw['kind'] ?? LearnerContact::KIND_EMERGENCY));
            if (!in_array($kind, LearnerContact::kinds(), true)) {
                $kind = LearnerContact::KIND_OTHER;
            }

            $contact = new LearnerContact();
            $contact->organisation_id = (int) $learner->organisation_id;
            $contact->learner_id = (int) $learner->id;
            $contact->kind = $kind;
            $contact->name = mb_substr($name, 0, 120);
            $phone = trim((string) ($raw['phone'] ?? ''));
            $contact->phone = $phone === '' ? null : mb_substr($phone, 0, 32);
            $email = trim((string) ($raw['email'] ?? ''));
            $contact->email = $email === '' ? null : mb_substr($email, 0, 255);
            $rel = trim((string) ($raw['relationship'] ?? ''));
            $contact->relationship = $rel === '' ? null : mb_substr($rel, 0, 64);
            $notes = trim((string) ($raw['notes'] ?? ''));
            $contact->notes = $notes === '' ? null : mb_substr($notes, 0, 255);
            $contact->sort_order = $order++;
            $contact->created_at = $now;
            $contact->updated_at = $now;

            if (!$contact->save()) {
                throw new BadRequestHttpException($this->firstError($contact) ?: 'Could not save contact.');
            }

            if ($kind === LearnerContact::KIND_EMERGENCY && $firstEmergency === null) {
                $firstEmergency = $contact;
            }
        }

        if ($firstEmergency !== null) {
            $learner->emergency_contact_name = $firstEmergency->name;
            $learner->emergency_contact_phone = $firstEmergency->phone;
        } else {
            $learner->emergency_contact_name = null;
            $learner->emergency_contact_phone = null;
        }
    }

    /**
     * Keep contacts table aligned when only legacy emergency fields are patched.
     */
    private function syncLegacyEmergency(Learner $learner): void
    {
        $name = trim((string) ($learner->emergency_contact_name ?? ''));
        $phone = trim((string) ($learner->emergency_contact_phone ?? ''));

        /** @var LearnerContact|null $existing */
        $existing = LearnerContact::find()
            ->andWhere([
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
                'kind' => LearnerContact::KIND_EMERGENCY,
            ])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->one();

        if ($name === '') {
            if ($existing !== null) {
                $existing->delete();
            }
            $learner->emergency_contact_name = null;
            $learner->emergency_contact_phone = null;

            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        if ($existing === null) {
            $existing = new LearnerContact();
            $existing->organisation_id = (int) $learner->organisation_id;
            $existing->learner_id = (int) $learner->id;
            $existing->kind = LearnerContact::KIND_EMERGENCY;
            $existing->sort_order = 0;
            $existing->created_at = $now;
        }
        $existing->name = mb_substr($name, 0, 120);
        $existing->phone = $phone === '' ? null : mb_substr($phone, 0, 32);
        $existing->updated_at = $now;
        $existing->save(false);
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors ? (string) reset($errors) : 'Unable to save.';
    }
}
