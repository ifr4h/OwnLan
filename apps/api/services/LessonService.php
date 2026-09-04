<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\Learner;
use app\models\Lesson;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Lean lesson booking — create, edit, list, cancel. No diary calendar.
 */
class LessonService
{
    /**
     * Upcoming scheduled lessons for the active organisation.
     *
     * @return list<array<string, mixed>>
     */
    public function listUpcoming(): array
    {
        $org = $this->requireOrganisation();
        $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $nowUtc])
            ->with(['learner', 'instructor'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        return array_map(fn (Lesson $lesson) => $this->toListArray($lesson, $org), $lessons);
    }

    /**
     * Smart Diary foundation — day or week slice in organisation local time.
     *
     * @return array<string, mixed>
     */
    public function diary(string $view, ?string $dateLocal, ?DateTimeImmutable $nowUtc = null): array
    {
        $org = $this->requireOrganisation();
        $tz = OrganisationTime::timezoneFor($org);
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowLocal = $nowUtc->setTimezone($tz);

        $view = strtolower(trim($view));
        if (!in_array($view, ['day', 'week', 'month'], true)) {
            throw new BadRequestHttpException('Diary view must be day, week or month.');
        }

        $anchor = $this->resolveDiaryAnchor($dateLocal, $nowLocal, $tz);
        if ($view === 'day') {
            $rangeStart = $anchor->setTime(0, 0, 0);
            $rangeEnd = $rangeStart->modify('+1 day');
            $label = $rangeStart->format('l j F Y');
        } elseif ($view === 'week') {
            // ISO week: Monday start (UK teaching diary convention).
            $dow = (int) $anchor->format('N'); // 1=Mon … 7=Sun
            $rangeStart = $anchor->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
            $rangeEnd = $rangeStart->modify('+7 days');
            $weekEnd = $rangeEnd->modify('-1 day');
            $label = $rangeStart->format('j M') . ' – ' . $weekEnd->format('j M Y');
        } else {
            // Month grid: Mon of week containing 1st → Sun of week containing last day.
            $monthStart = $anchor->modify('first day of this month')->setTime(0, 0, 0);
            $monthEndExclusive = $anchor->modify('first day of next month')->setTime(0, 0, 0);
            $dow = (int) $monthStart->format('N');
            $rangeStart = $monthStart->modify('-' . ($dow - 1) . ' days');
            $lastDay = $monthEndExclusive->modify('-1 day');
            $dowLast = (int) $lastDay->format('N');
            $rangeEnd = $lastDay->modify('+' . (7 - $dowLast) . ' days')->modify('+1 day')->setTime(0, 0, 0);
            $label = $anchor->format('F Y');
        }

        $startUtc = $rangeStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $rangeEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['>=', 'starts_at', $startUtc])
            ->andWhere(['<', 'starts_at', $endUtc])
            ->with(['learner', 'instructor'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $items = array_map(
            fn (Lesson $lesson) => $this->serializeForDiary($lesson, $org, $nowUtc),
            $lessons,
        );
        $items = $this->markDiaryOverlaps($items);
        $travel = new TravelFeasibilityService();
        $gapMatcher = new GapMatchingService();

        $days = [];
        $cursor = $rangeStart;
        $anchorMonth = $anchor->format('Y-m');
        while ($cursor < $rangeEnd) {
            $key = $cursor->format('Y-m-d');
            $dayLessons = array_values(array_filter(
                $items,
                static fn (array $item) => str_starts_with((string) $item['starts_at_local'], $key),
            ));

            if ($view === 'month') {
                // Lightweight month cells — no gap matching (performance).
                $teachingMinutes = 0;
                $hasTest = false;
                $hasCancel = false;
                $hasOverlap = false;
                $markers = [];
                foreach ($dayLessons as $lesson) {
                    $status = (string) ($lesson['status'] ?? '');
                    if ($status === Lesson::STATUS_SCHEDULED || $status === Lesson::STATUS_COMPLETED) {
                        $teachingMinutes += (int) ($lesson['duration_minutes'] ?? 0);
                    }
                    if ($status === Lesson::STATUS_CANCELLED) {
                        $hasCancel = true;
                    }
                    if (!empty($lesson['overlaps'])) {
                        $hasOverlap = true;
                    }
                    $tj = $lesson['test_journey'] ?? null;
                    if (is_array($tj) && !empty($tj['countdown_label'])) {
                        $hasTest = true;
                    }
                    $markers[] = [
                        'id' => (int) $lesson['id'],
                        'starts_at_time' => $lesson['starts_at_time'] ?? null,
                        'duration_minutes' => (int) ($lesson['duration_minutes'] ?? 0),
                        'learner_name' => $lesson['learner_name'] ?? null,
                        'status' => $status,
                        'overlaps' => !empty($lesson['overlaps']),
                    ];
                }
                $days[] = [
                    'date' => $key,
                    'date_display' => $cursor->format('j'),
                    'weekday' => $cursor->format('l'),
                    'is_today' => $key === $nowLocal->format('Y-m-d'),
                    'in_month' => $cursor->format('Y-m') === $anchorMonth,
                    'lesson_count' => count($dayLessons),
                    'teaching_minutes' => $teachingMinutes,
                    'has_test' => $hasTest,
                    'has_cancellation' => $hasCancel,
                    'has_overlap' => $hasOverlap,
                    'markers' => $markers,
                    'lessons' => [],
                    'gaps' => [],
                ];
            } else {
                $dayLessons = $travel->annotateDiaryItems($dayLessons);
                $gaps = $gapMatcher->gapsForDay($org, $key, $dayLessons, $nowUtc);
                $days[] = [
                    'date' => $key,
                    'date_display' => $cursor->format('D j M'),
                    'weekday' => $cursor->format('l'),
                    'is_today' => $key === $nowLocal->format('Y-m-d'),
                    'lesson_count' => count($dayLessons),
                    'lessons' => $dayLessons,
                    'gaps' => $gaps,
                ];
            }
            $cursor = $cursor->modify('+1 day');
        }

        $flat = [];
        $gapCount = 0;
        foreach ($days as $day) {
            foreach ($day['lessons'] as $lesson) {
                $flat[] = $lesson;
            }
            $gapCount += count($day['gaps'] ?? []);
        }

        $travelWarningCount = 0;
        foreach ($flat as $item) {
            $leg = $item['travel_to_next'] ?? null;
            if (is_array($leg) && !empty($leg['is_warning'])) {
                $travelWarningCount++;
            }
        }

        return [
            'view' => $view,
            'date' => $anchor->format('Y-m-d'),
            'range_start' => $rangeStart->format('Y-m-d'),
            'range_end' => $rangeEnd->modify('-1 day')->format('Y-m-d'),
            'label' => $label,
            'timezone' => $org->timezone,
            'is_today' => $anchor->format('Y-m-d') === $nowLocal->format('Y-m-d'),
            'now_local' => OrganisationTime::formatLocalIso($nowLocal),
            'now_time' => $nowLocal->format('H:i'),
            'work_start_time' => $org->work_start_time ?: Organisation::DEFAULT_WORK_START,
            'work_end_time' => $org->work_end_time ?: Organisation::DEFAULT_WORK_END,
            'work_days' => $org->workDays(),
            'days' => $days,
            'lessons' => $view === 'month' ? [] : $flat,
            'overlap_count' => count(array_filter(
                $view === 'month' ? $items : $flat,
                static fn (array $i) => !empty($i['overlaps']),
            )),
            'travel_warning_count' => $travelWarningCount,
            'gap_count' => $gapCount,
        ];
    }

    /**
     * @throws BadRequestHttpException
     */
    private function resolveDiaryAnchor(?string $dateLocal, DateTimeImmutable $nowLocal, DateTimeZone $tz): DateTimeImmutable
    {
        if ($dateLocal === null || trim($dateLocal) === '') {
            return $nowLocal->setTime(0, 0, 0);
        }
        $date = trim($dateLocal);
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);
        $errors = DateTimeImmutable::getLastErrors();
        $bad = $parsed === false
            || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $parsed->format('Y-m-d') !== $date;
        if ($bad) {
            throw new BadRequestHttpException('Diary date must be YYYY-MM-DD.');
        }

        return $parsed->setTime(0, 0, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeForDiary(
        Lesson $lesson,
        Organisation $organisation,
        DateTimeImmutable $nowUtc,
    ): array {
        $payload = $this->serialize($lesson, $organisation, compact: true);
        $startsUtc = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
        $endsUtc = $startsUtc->modify('+' . (int) $lesson->duration_minutes . ' minutes');

        $payload['is_current'] = $lesson->status === Lesson::STATUS_SCHEDULED
            && $startsUtc <= $nowUtc
            && $nowUtc < $endsUtc;
        $payload['overlaps'] = false;
        $payload['overlap_with'] = [];

        return $payload;
    }

    /**
     * Flag scheduled lessons whose time ranges intersect.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function markDiaryOverlaps(array $items): array
    {
        $n = count($items);
        for ($i = 0; $i < $n; $i++) {
            if ($items[$i]['status'] !== Lesson::STATUS_SCHEDULED) {
                continue;
            }
            $aStart = new DateTimeImmutable((string) $items[$i]['starts_at']);
            $aEnd = new DateTimeImmutable((string) $items[$i]['ends_at']);
            for ($j = $i + 1; $j < $n; $j++) {
                if ($items[$j]['status'] !== Lesson::STATUS_SCHEDULED) {
                    continue;
                }
                $bStart = new DateTimeImmutable((string) $items[$j]['starts_at']);
                $bEnd = new DateTimeImmutable((string) $items[$j]['ends_at']);
                if ($aStart < $bEnd && $bStart < $aEnd) {
                    $items[$i]['overlaps'] = true;
                    $items[$j]['overlaps'] = true;
                    $items[$i]['overlap_with'][] = (int) $items[$j]['id'];
                    $items[$j]['overlap_with'][] = (int) $items[$i]['id'];
                }
            }
        }

        return $items;
    }

    /**
     * All lessons for one pupil (newest first) — history + upcoming.
     *
     * @return list<array<string, mixed>>
     */
    public function listForLearner(int $learnerId): array
    {
        $org = $this->requireOrganisation();
        $this->findLearnerOwned($learnerId);

        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['learner_id' => $learnerId])
            ->with(['learner', 'instructor'])
            ->orderBy(['starts_at' => SORT_DESC])
            ->all();

        return array_map(fn (Lesson $lesson) => $this->toListArray($lesson, $org), $lessons);
    }

    /**
     * Next scheduled lesson for a pupil, if any.
     *
     * @return array<string, mixed>|null
     */
    public function nextForLearner(int $learnerId): ?array
    {
        $org = $this->requireOrganisation();
        $this->findLearnerOwned($learnerId);
        $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        /** @var Lesson|null $lesson */
        $lesson = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>=', 'starts_at', $nowUtc])
            ->with(['learner', 'instructor'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->one();

        return $lesson ? $this->toListArray($lesson, $org) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        $org = $this->requireOrganisation();
        $lesson = $this->findOwned($id);
        $lesson->populateRelation('learner', $lesson->learner);
        $lesson->populateRelation('instructor', $lesson->instructor);

        $payload = $this->toApiArray($lesson, $org);
        if ($lesson->status === Lesson::STATUS_CANCELLED) {
            $payload['empty_seat'] = (new EmptySeatService())->forCancelledLesson($lesson, $org);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $org = $this->requireOrganisation();
        $instructor = $this->requireCurrentInstructor($org);

        $learnerId = (int) ($data['learner_id'] ?? 0);
        $learner = $this->findLearnerOwned($learnerId);

        $lesson = new Lesson();
        $lesson->organisation_id = (int) $org->id;
        $lesson->instructor_id = (int) $instructor->id;
        $lesson->learner_id = (int) $learner->id;
        $lesson->status = Lesson::STATUS_SCHEDULED;
        $lesson->duration_minutes = $this->resolveDuration($data['duration_minutes'] ?? null);
        $lesson->starts_at = OrganisationTime::formatUtc(
            OrganisationTime::localToUtc((string) ($data['starts_at_local'] ?? ''), $org),
        );
        $lesson->pickup_address = $this->resolvePickup($data['pickup_address'] ?? null, $learner);

        $now = gmdate('Y-m-d H:i:s');
        $lesson->created_at = $now;
        $lesson->updated_at = $now;

        if (!$lesson->save()) {
            throw new BadRequestHttpException($this->firstError($lesson));
        }

        $lesson->populateRelation('learner', $learner);
        $lesson->populateRelation('instructor', $instructor);

        return $this->withTravelWarnings(
            $this->toApiArray($lesson, $org),
            $org,
            $lesson,
            $learner->fullName,
        );
    }

    /**
     * Non-blocking travel check for create/edit forms.
     *
     * @param array<string, mixed> $data
     * @return array{warnings: list<array<string, mixed>>, warning_count: int, has_warnings: bool}
     */
    public function travelCheck(array $data): array
    {
        $org = $this->requireOrganisation();
        $learnerId = (int) ($data['learner_id'] ?? 0);
        $learner = $learnerId > 0 ? $this->findLearnerOwned($learnerId) : null;
        $duration = $this->resolveDuration($data['duration_minutes'] ?? null);

        if ($learner !== null) {
            $pickup = $this->resolvePickup($data['pickup_address'] ?? null, $learner);
        } else {
            $raw = $data['pickup_address'] ?? null;
            $pickup = ($raw === null || trim((string) $raw) === '') ? null : trim((string) $raw);
        }

        $startsAtUtc = OrganisationTime::formatUtc(
            OrganisationTime::localToUtc((string) ($data['starts_at_local'] ?? ''), $org),
        );
        $excludeId = isset($data['exclude_lesson_id']) && $data['exclude_lesson_id'] !== ''
            && $data['exclude_lesson_id'] !== null
            ? (int) $data['exclude_lesson_id']
            : null;

        $warnings = (new TravelFeasibilityService())->checkProposed(
            $org,
            $startsAtUtc,
            $duration,
            $pickup,
            $learner?->fullName,
            $excludeId,
        );
        $hoursWarning = $this->workingHoursWarning($org, $startsAtUtc, $duration);
        if ($hoursWarning !== null) {
            $warnings[] = $hoursWarning;
        }

        return [
            'warnings' => $warnings,
            'warning_count' => count($warnings),
            'has_warnings' => $warnings !== [],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $org = $this->requireOrganisation();
        $lesson = $this->findOwned($id);

        if (!$lesson->isScheduled()) {
            throw new BadRequestHttpException('Only scheduled lessons can be edited.');
        }

        if (array_key_exists('learner_id', $data)) {
            $learner = $this->findLearnerOwned((int) $data['learner_id']);
            $lesson->learner_id = (int) $learner->id;
        } else {
            $learner = $this->findLearnerOwned((int) $lesson->learner_id);
        }

        if (array_key_exists('starts_at_local', $data)) {
            $lesson->starts_at = OrganisationTime::formatUtc(
                OrganisationTime::localToUtc((string) $data['starts_at_local'], $org),
            );
        }

        if (array_key_exists('duration_minutes', $data)) {
            $lesson->duration_minutes = $this->resolveDuration($data['duration_minutes']);
        }

        if (array_key_exists('pickup_address', $data)) {
            $lesson->pickup_address = $this->resolvePickup($data['pickup_address'], $learner, allowEmpty: true);
        }

        $lesson->updated_at = gmdate('Y-m-d H:i:s');

        if (!$lesson->save()) {
            throw new BadRequestHttpException($this->firstError($lesson));
        }

        $lesson->refresh();
        $lesson->populateRelation('learner', $learner);
        $lesson->populateRelation('instructor', $lesson->instructor);

        return $this->withTravelWarnings(
            $this->toApiArray($lesson, $org),
            $org,
            $lesson,
            $learner->fullName,
        );
    }

    /**
     * Soft-cancel — keeps the row for history / later pattern derivation.
     *
     * Optional charge for late cancellation: charge waived|outstanding|package.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function cancel(int $id, array $data = []): array
    {
        $org = $this->requireOrganisation();
        $lesson = $this->findOwned($id);

        if ($lesson->status === Lesson::STATUS_CANCELLED) {
            throw new BadRequestHttpException('This lesson is already cancelled.');
        }
        if ($lesson->status === Lesson::STATUS_COMPLETED) {
            throw new BadRequestHttpException('Completed lessons cannot be cancelled.');
        }
        if ($lesson->status === Lesson::STATUS_NO_SHOW) {
            throw new BadRequestHttpException('No-show lessons cannot be cancelled.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $lesson->status = Lesson::STATUS_CANCELLED;
        $lesson->cancelled_at = $now;
        $lesson->updated_at = $now;

        $finance = null;
        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$lesson->save(true, ['status', 'cancelled_at', 'updated_at'])) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }

            $finance = (new FinanceService())->settleCancelledLesson($lesson, $org, $data);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $lesson->refresh();
        $lesson->populateRelation('learner', $lesson->learner);
        $lesson->populateRelation('instructor', $lesson->instructor);

        $payload = $this->toApiArray($lesson, $org);
        if ($finance !== null) {
            $payload['finance'] = $finance;
        }
        $payload['empty_seat'] = (new EmptySeatService())->forCancelledLesson($lesson, $org);

        return $payload;
    }

    /**
     * Mark a scheduled lesson as no-show (pupil did not attend).
     *
     * Does not complete the lesson or create progress. Financial outcome is explicit.
     *
     * @param array<string, mixed> $data charge: waived|outstanding|package, instructor_notes?, client_mutation_id?
     * @return array<string, mixed>
     */
    public function markNoShow(int $id, array $data = []): array
    {
        $org = $this->requireOrganisation();
        $mutationId = $this->normalizeMutationId($data['client_mutation_id'] ?? null);

        if ($mutationId !== null) {
            /** @var Lesson|null $replay */
            $replay = TenantContext::scopeByOrganisation(Lesson::find())
                ->andWhere(['client_mutation_id' => $mutationId])
                ->with(['learner', 'instructor'])
                ->one();
            if ($replay !== null) {
                $payload = $this->toApiArray($replay, $org);
                $payload['finance'] = (new FinanceService())->settleNoShowLesson($replay, $org, $data);

                return $payload;
            }
        }

        $lesson = $this->findOwned($id);

        if ($lesson->status === Lesson::STATUS_NO_SHOW) {
            $lesson->populateRelation('learner', $lesson->learner);
            $lesson->populateRelation('instructor', $lesson->instructor);
            $payload = $this->toApiArray($lesson, $org);
            $payload['finance'] = (new FinanceService())->settleNoShowLesson($lesson, $org, $data);

            return $payload;
        }
        if ($lesson->status === Lesson::STATUS_COMPLETED) {
            throw new BadRequestHttpException('Completed lessons cannot be marked no-show.');
        }
        if ($lesson->status === Lesson::STATUS_CANCELLED) {
            throw new BadRequestHttpException('Cancelled lessons cannot be marked no-show.');
        }
        if ($lesson->status !== Lesson::STATUS_SCHEDULED) {
            throw new BadRequestHttpException('Only scheduled lessons can be marked no-show.');
        }

        $startsUtc = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($startsUtc > $nowUtc) {
            throw new BadRequestHttpException('This lesson has not started yet.');
        }

        $instructorNotes = $this->nullableText($data['instructor_notes'] ?? null);
        $now = gmdate('Y-m-d H:i:s');
        $lesson->status = Lesson::STATUS_NO_SHOW;
        $lesson->no_show_at = $now;
        $lesson->updated_at = $now;
        $lesson->client_mutation_id = $mutationId;
        if ($instructorNotes !== null) {
            $lesson->instructor_notes = $instructorNotes;
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$lesson->save()) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }

            $finance = (new FinanceService())->settleNoShowLesson($lesson, $org, $data);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $lesson->refresh();
        $lesson->populateRelation('learner', $lesson->learner);
        $lesson->populateRelation('instructor', $lesson->instructor);

        $payload = $this->toApiArray($lesson, $org);
        $payload['finance'] = $finance;

        return $payload;
    }

    /**
     * Complete a scheduled lesson with optional teaching context.
     *
     * Idempotent when the same client_mutation_id is replayed (offline outbox retries).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function complete(int $id, array $data = []): array
    {
        $org = $this->requireOrganisation();
        $mutationId = $this->normalizeMutationId($data['client_mutation_id'] ?? null);

        if ($mutationId !== null) {
            /** @var Lesson|null $replay */
            $replay = TenantContext::scopeByOrganisation(Lesson::find())
                ->andWhere(['client_mutation_id' => $mutationId])
                ->with(['learner', 'instructor'])
                ->one();
            if ($replay !== null) {
                return $this->toApiArray($replay, $org);
            }
        }

        $lesson = $this->findOwned($id);

        if ($lesson->status === Lesson::STATUS_COMPLETED) {
            // Already completed without this mutation id — treat as success for retries.
            $lesson->populateRelation('learner', $lesson->learner);
            $lesson->populateRelation('instructor', $lesson->instructor);

            return $this->toApiArray($lesson, $org);
        }
        if ($lesson->status === Lesson::STATUS_CANCELLED) {
            throw new BadRequestHttpException('Cancelled lessons cannot be completed.');
        }
        if ($lesson->status === Lesson::STATUS_NO_SHOW) {
            throw new BadRequestHttpException('No-show lessons cannot be completed.');
        }

        $instructorNotes = $this->nullableText($data['instructor_notes'] ?? null);
        $learnerSummary = $this->nullableText($data['learner_summary'] ?? null);
        $nextFocus = $this->nullableText($data['next_focus'] ?? null);

        $now = gmdate('Y-m-d H:i:s');
        $lesson->status = Lesson::STATUS_COMPLETED;
        $lesson->completed_at = $now;
        $lesson->updated_at = $now;
        $lesson->instructor_notes = $instructorNotes;
        $lesson->learner_summary = $learnerSummary;
        $lesson->next_focus = $nextFocus;
        $lesson->client_mutation_id = $mutationId;

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$lesson->save()) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }

            $learner = TenantContext::scopeByOrganisation(Learner::find())
                ->andWhere(['id' => (int) $lesson->learner_id])
                ->one();
            if ($learner instanceof Learner) {
                if ($nextFocus !== null) {
                    $learner->next_focus = $nextFocus;
                }
                if ($learnerSummary !== null) {
                    $learner->last_lesson_summary = $learnerSummary;
                }
                if ($nextFocus !== null || $learnerSummary !== null) {
                    $learner->updated_at = $now;
                    if (!$learner->save(true, ['next_focus', 'last_lesson_summary', 'updated_at'])) {
                        throw new BadRequestHttpException($this->firstError($learner));
                    }
                }
            }

            $finance = (new FinanceService())->settleCompletedLesson($lesson, $org, $data);

            $skillIds = is_array($data['skill_ids'] ?? null) ? $data['skill_ids'] : [];
            $ratings = is_array($data['skill_ratings'] ?? null) ? $data['skill_ratings'] : [];
            if ($skillIds !== [] || $ratings !== []) {
                (new ProgressService())->syncLessonSkills($lesson, $skillIds, $ratings);
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $lesson->refresh();
        $lesson->populateRelation('learner', $lesson->learner);
        $lesson->populateRelation('instructor', $lesson->instructor);

        $payload = $this->toApiArray($lesson, $org);
        $payload['finance'] = $finance;
        $payload['skills'] = (new ProgressService())->skillsForLesson((int) $lesson->id, (int) $org->id);

        return $payload;
    }

    private function normalizeMutationId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = trim((string) $value);
        if ($id === '' || strlen($id) > 64) {
            throw new BadRequestHttpException('Invalid client mutation id.');
        }

        return $id;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Instructor "Today" cockpit for the organisation's local calendar day.
     *
     * @return array<string, mixed>
     */
    public function today(?DateTimeImmutable $nowUtc = null): array
    {
        $org = $this->requireOrganisation();
        $tz = OrganisationTime::timezoneFor($org);
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowLocal = $nowUtc->setTimezone($tz);

        $dayStartLocal = $nowLocal->setTime(0, 0, 0);
        $dayEndLocal = $dayStartLocal->modify('+1 day');
        $dayStartUtc = $dayStartLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $dayEndUtc = $dayEndLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['>=', 'starts_at', $dayStartUtc])
            ->andWhere(['<', 'starts_at', $dayEndUtc])
            ->with(['learner', 'instructor'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $serialized = array_map(
            fn (Lesson $lesson) => $this->serializeForToday($lesson, $org, $nowUtc),
            $lessons,
        );
        $serialized = $this->markNextLesson($serialized, $nowUtc);
        $serialized = (new TravelFeasibilityService())->annotateDiaryItems($serialized);

        $focus = null;
        foreach ($serialized as $item) {
            if ($item['status'] === Lesson::STATUS_SCHEDULED && ($item['is_current'] || $item['is_next'])) {
                $focus = $item;
                break;
            }
        }

        $remaining = [];
        if ($focus !== null) {
            $seenFocus = false;
            foreach ($serialized as $item) {
                if ((int) $item['id'] === (int) $focus['id']) {
                    $seenFocus = true;
                    continue;
                }
                if ($seenFocus && $item['status'] === Lesson::STATUS_SCHEDULED) {
                    $remaining[] = $item;
                }
            }
        }

        $brief = new MorningBriefService();
        $summary = $brief->daySummary($serialized);
        $needsYou = $brief->needsYou($org, $nowUtc);

        if ($focus !== null) {
            $focus['finance'] = (new FinanceService())->compactSnapshot((int) $focus['learner_id']);
        }

        return [
            'date' => $dayStartLocal->format('Y-m-d'),
            'date_display' => $dayStartLocal->format('l j F'),
            'timezone' => $org->timezone,
            'now_time' => $nowLocal->format('H:i'),
            'lesson_count' => $summary['lesson_count'],
            'summary' => $summary,
            'focus' => $focus,
            'remaining' => $remaining,
            'lessons' => $serialized,
            'needs_you' => $needsYou,
            'needs_attention' => (new ContinuityService())->needsAttention($nowUtc),
        ];
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

    /**
     * @throws BadRequestHttpException
     */
    private function requireCurrentInstructor(Organisation $organisation): Instructor
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }

        $instructor = Instructor::find()
            ->where([
                'organisation_id' => (int) $organisation->id,
                'user_id' => (int) Yii::$app->user->id,
            ])
            ->one();

        if ($instructor === null) {
            throw new BadRequestHttpException('No instructor profile for this account.');
        }

        return $instructor;
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findOwned(int $id): Lesson
    {
        /** @var Lesson|null $lesson */
        $lesson = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['id' => $id])
            ->one();

        if ($lesson === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        return $lesson;
    }

    /**
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    private function findLearnerOwned(int $learnerId): Learner
    {
        if ($learnerId < 1) {
            throw new BadRequestHttpException('Choose a pupil.');
        }

        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();

        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        if ($learner->isArchived) {
            throw new BadRequestHttpException('This pupil is archived.');
        }

        return $learner;
    }

    /**
     * @throws BadRequestHttpException
     */
    private function resolveDuration(mixed $value): int
    {
        if ($value === null || $value === '') {
            return $this->requireOrganisation()->defaultLessonDurationMinutes();
        }
        $minutes = (int) $value;
        if ($minutes < 15 || $minutes > 480) {
            throw new BadRequestHttpException('Duration must be between 15 and 480 minutes.');
        }

        return $minutes;
    }

    private function resolvePickup(mixed $value, Learner $learner, bool $allowEmpty = false): ?string
    {
        if ($value === null) {
            return $learner->default_pickup_address;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return $allowEmpty ? null : $learner->default_pickup_address;
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withTravelWarnings(
        array $payload,
        Organisation $organisation,
        Lesson $lesson,
        ?string $learnerName,
    ): array {
        $warnings = (new TravelFeasibilityService())->checkProposed(
            $organisation,
            $lesson->starts_at,
            (int) $lesson->duration_minutes,
            $lesson->pickup_address,
            $learnerName,
            (int) $lesson->id,
        );
        $hoursWarning = $this->workingHoursWarning(
            $organisation,
            $lesson->starts_at,
            (int) $lesson->duration_minutes,
        );
        if ($hoursWarning !== null) {
            $warnings[] = $hoursWarning;
        }
        $payload['travel_warnings'] = $warnings;

        return $payload;
    }

    /**
     * Soft advisory only — instructors may still book outside usual hours.
     *
     * @return array<string, mixed>|null
     */
    private function workingHoursWarning(
        Organisation $organisation,
        string|DateTimeImmutable $startsAtUtc,
        int $durationMinutes,
    ): ?array {
        $local = OrganisationTime::utcToLocal($startsAtUtc, $organisation);
        if ($organisation->isWithinWorkingHours($local, $durationMinutes)) {
            return null;
        }

        $days = $organisation->workDays();
        $labels = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dayBits = [];
        foreach ($days as $d) {
            $dayBits[] = $labels[$d] ?? (string) $d;
        }

        return [
            'code' => 'outside_working_hours',
            'severity' => 'soft',
            'message' => 'Outside your usual hours ('
                . implode(', ', $dayBits)
                . ' '
                . $organisation->workStartTime()
                . '–'
                . $organisation->workEndTime()
                . '). You can still book this.',
            'is_warning' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toListArray(Lesson $lesson, Organisation $organisation): array
    {
        return $this->serialize($lesson, $organisation, compact: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(Lesson $lesson, Organisation $organisation): array
    {
        return $this->serialize($lesson, $organisation, compact: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Lesson $lesson, Organisation $organisation, bool $compact): array
    {
        $local = OrganisationTime::utcToLocal($lesson->starts_at, $organisation);
        $endsLocal = $local->modify('+' . (int) $lesson->duration_minutes . ' minutes');
        $startsUtc = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
        $endsUtc = $startsUtc->modify('+' . (int) $lesson->duration_minutes . ' minutes');
        $learner = $lesson->learner;

        $payload = [
            'id' => (int) $lesson->id,
            'learner_id' => (int) $lesson->learner_id,
            'instructor_id' => (int) $lesson->instructor_id,
            'series_id' => $lesson->series_id !== null ? (int) $lesson->series_id : null,
            'learner_name' => $learner?->fullName,
            'learner_mobile' => $learner?->mobile,
            'starts_at' => $startsUtc->format(DATE_ATOM),
            'starts_at_local' => OrganisationTime::formatLocalIso($local),
            'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
            'starts_at_time' => $local->format('H:i'),
            'ends_at' => $endsUtc->format(DATE_ATOM),
            'ends_at_local' => OrganisationTime::formatLocalIso($endsLocal),
            'ends_at_time' => $endsLocal->format('H:i'),
            'timezone' => $organisation->timezone,
            'duration_minutes' => (int) $lesson->duration_minutes,
            'pickup_address' => $lesson->pickup_address,
            'status' => $lesson->status,
            'price_pence' => $lesson->price_pence !== null ? (int) $lesson->price_pence : null,
            'settlement' => $lesson->settlement,
            'instructor_notes' => $lesson->instructor_notes,
            'learner_summary' => $lesson->learner_summary,
            'next_focus' => $lesson->next_focus,
            'client_mutation_id' => $lesson->client_mutation_id,
            'learner_next_focus' => $learner?->next_focus,
            'learner_last_lesson_summary' => $learner?->last_lesson_summary,
            'test_journey' => ($learner !== null)
                ? (new TestJourneyService())->buildCompact($learner, $organisation)
                : null,
            'cancelled_at' => $lesson->cancelled_at,
            'completed_at' => $lesson->completed_at,
            'no_show_at' => $lesson->no_show_at,
            'status_label' => $this->statusLabel((string) $lesson->status),
            'financial_line' => (new FinanceService())->financialLineForLesson($lesson),
            'can_mark_no_show' => $this->canMarkNoShow($lesson),
            'can_complete' => $lesson->status === Lesson::STATUS_SCHEDULED,
        ];

        if (!$compact) {
            $payload['created_at'] = $lesson->created_at;
            $payload['updated_at'] = $lesson->updated_at;
            // Full journey on lesson detail when a test date exists (even beyond Today horizon).
            if ($learner !== null) {
                $payload['test_journey'] = (new TestJourneyService())->build($learner, $organisation);
            }
            $finance = new FinanceService();
            $payload['finance'] = [
                'snapshot' => $finance->compactSnapshot((int) $lesson->learner_id),
                'settlement' => $lesson->settlement,
                'aftermath' => null,
            ];
            if ($lesson->status === Lesson::STATUS_COMPLETED) {
                $payload['finance'] = $finance->withCompletionAftermath($lesson, [
                    'settlement' => $lesson->settlement,
                    'package_usage' => null,
                    'charge' => null,
                    'payment' => null,
                ]);
                $payload['finance']['snapshot'] = $finance->compactSnapshot((int) $lesson->learner_id);
            } elseif ($lesson->status === Lesson::STATUS_NO_SHOW) {
                $payload['finance'] = $finance->withNoShowAftermath($lesson, [
                    'settlement' => (string) ($lesson->settlement ?? FinanceService::SETTLEMENT_WAIVED),
                    'package_usage' => null,
                    'charge' => null,
                    'payment' => null,
                ]);
                $payload['finance']['snapshot'] = $finance->compactSnapshot((int) $lesson->learner_id);
            }
        }

        return $payload;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Lesson::STATUS_CANCELLED => 'Cancelled',
            Lesson::STATUS_COMPLETED => 'Completed',
            Lesson::STATUS_NO_SHOW => 'No-show',
            default => 'Scheduled',
        };
    }

    private function canMarkNoShow(Lesson $lesson): bool
    {
        if ($lesson->status !== Lesson::STATUS_SCHEDULED) {
            return false;
        }
        $startsUtc = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $startsUtc <= $nowUtc;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeForToday(
        Lesson $lesson,
        Organisation $organisation,
        DateTimeImmutable $nowUtc,
    ): array {
        $payload = $this->serialize($lesson, $organisation, compact: true);
        $startsUtc = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
        $endsUtc = $startsUtc->modify('+' . (int) $lesson->duration_minutes . ' minutes');

        $isCurrent = $lesson->status === Lesson::STATUS_SCHEDULED
            && $startsUtc <= $nowUtc
            && $nowUtc < $endsUtc;

        $payload['is_current'] = $isCurrent;
        $payload['is_next'] = false;
        $payload['can_complete'] = $lesson->status === Lesson::STATUS_SCHEDULED;
        $payload['can_mark_no_show'] = $this->canMarkNoShow($lesson);

        return $payload;
    }

    /**
     * Mark is_next on the first future scheduled lesson when nothing is current.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function markNextLesson(array $items, DateTimeImmutable $nowUtc): array
    {
        $hasCurrent = false;
        foreach ($items as $item) {
            if (!empty($item['is_current'])) {
                $hasCurrent = true;
                break;
            }
        }

        if ($hasCurrent) {
            return $items;
        }

        foreach ($items as $i => $item) {
            if (
                $item['status'] === Lesson::STATUS_SCHEDULED
                && new DateTimeImmutable($item['starts_at']) >= $nowUtc
            ) {
                $items[$i]['is_next'] = true;
                break;
            }
        }

        return $items;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors ? (string) reset($errors) : 'Unable to save.';
    }
}
