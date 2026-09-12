<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\PortalContext;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\Learner;
use app\models\LearnerLocation;
use app\models\Lesson;
use app\models\Organisation;
use app\models\OrganisationService;
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
        $weekStartsOn = $org->weekStartsOn();
        if ($view === 'day') {
            $rangeStart = $anchor->setTime(0, 0, 0);
            $rangeEnd = $rangeStart->modify('+1 day');
            $label = $rangeStart->format('l j F Y');
        } elseif ($view === 'week') {
            $dow = (int) $anchor->format('N'); // 1=Mon … 7=Sun
            $offset = ($dow - $weekStartsOn + 7) % 7;
            $rangeStart = $anchor->modify('-' . $offset . ' days')->setTime(0, 0, 0);
            $rangeEnd = $rangeStart->modify('+7 days');
            $weekEnd = $rangeEnd->modify('-1 day');
            $label = $rangeStart->format('j M') . ' – ' . $weekEnd->format('j M Y');
        } else {
            // Month grid: week-start of week containing 1st → week-end of week containing last day.
            $monthStart = $anchor->modify('first day of this month')->setTime(0, 0, 0);
            $monthEndExclusive = $anchor->modify('first day of next month')->setTime(0, 0, 0);
            $dow = (int) $monthStart->format('N');
            $offset = ($dow - $weekStartsOn + 7) % 7;
            $rangeStart = $monthStart->modify('-' . $offset . ' days');
            $lastDay = $monthEndExclusive->modify('-1 day');
            $dowLast = (int) $lastDay->format('N');
            $endOffset = ($weekStartsOn + 6 - $dowLast + 7) % 7;
            $rangeEnd = $lastDay->modify('+' . $endOffset . ' days')->modify('+1 day')->setTime(0, 0, 0);
            $label = $anchor->format('F Y');
        }

        $startUtc = $rangeStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $rangeEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['>=', 'starts_at', $startUtc])
            ->andWhere(['<', 'starts_at', $endUtc])
            ->with(['learner', 'instructor', 'pickupLocation'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $items = array_map(
            fn (Lesson $lesson) => $this->serializeForDiary($lesson, $org, $nowUtc),
            $lessons,
        );
        $items = $this->markDiaryOverlaps($items);
        $blocks = (new DiaryBlockService())->listInRange($startUtc, $endUtc);
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
            $dayBlocks = array_values(array_filter(
                $blocks,
                static fn (array $block) => ($block['date'] ?? '') === $key,
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
                foreach ($dayBlocks as $block) {
                    $markers[] = [
                        'id' => 'block-' . (int) $block['id'],
                        'starts_at_time' => $block['starts_at_time'] ?? null,
                        'duration_minutes' => (int) ($block['duration_minutes'] ?? 0),
                        'learner_name' => $block['label'] ?? 'Private',
                        'status' => 'private',
                        'overlaps' => false,
                        'is_private_block' => true,
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
                    'blocks' => [],
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
                    'blocks' => $dayBlocks,
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
            'week_starts_on' => $weekStartsOn,
            'days' => $days,
            'lessons' => $view === 'month' ? [] : $flat,
            'blocks' => $view === 'month' ? [] : $blocks,
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
        $resolved = $this->resolveServiceAndDuration($org, $data);
        $lesson->service_id = $resolved['service_id'];
        $lesson->duration_minutes = $resolved['duration_minutes'];
        $lesson->starts_at = OrganisationTime::formatUtc(
            OrganisationTime::localToUtc((string) ($data['starts_at_local'] ?? ''), $org),
        );
        $this->applyPickupFields($lesson, $learner, $data, Lesson::PICKUP_BY_INSTRUCTOR);
        $this->applyFocusTags($lesson, $data['focus_tags'] ?? null);

        $now = gmdate('Y-m-d H:i:s');
        $lesson->created_at = $now;
        $lesson->updated_at = $now;

        if (!$lesson->save()) {
            throw new BadRequestHttpException($this->firstError($lesson));
        }

        if (!empty($data['test_details']) && is_array($data['test_details'])) {
            $this->applyTestDetailsFromBooking($learner, $data['test_details'], $lesson);
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
     * When booking a Test day lesson, update the pupil's DVSA test details.
     *
     * @param array<string, mixed> $details
     */
    private function applyTestDetailsFromBooking(Learner $learner, array $details, Lesson $lesson): void
    {
        $dirty = false;
        if (array_key_exists('practical_test_booking_ref', $details)) {
            $ref = trim((string) $details['practical_test_booking_ref']);
            $learner->practical_test_booking_ref = $ref === '' ? null : mb_substr($ref, 0, 64);
            $dirty = true;
        }
        if (array_key_exists('practical_test_cancel_by', $details)) {
            $cancel = trim((string) ($details['practical_test_cancel_by'] ?? ''));
            if ($cancel === '') {
                $learner->practical_test_cancel_by = null;
            } else {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $cancel);
                if ($dt !== false && $dt->format('Y-m-d') === $cancel) {
                    $learner->practical_test_cancel_by = $cancel;
                }
            }
            $dirty = true;
        }
        if (array_key_exists('practical_test_time', $details)) {
            $time = trim((string) ($details['practical_test_time'] ?? ''));
            $learner->practical_test_time = preg_match('/^\d{2}:\d{2}$/', $time) === 1 ? $time : null;
            $dirty = true;
        }
        if (trim((string) ($learner->test_date ?? '')) === '') {
            $org = Organisation::findOne(['id' => (int) $lesson->organisation_id]);
            if ($org !== null) {
                $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
                $learner->test_date = $local->format('Y-m-d');
                $dirty = true;
            }
        }
        if ($dirty) {
            $learner->updated_at = gmdate('Y-m-d H:i:s');
            $learner->save(false);
        }
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

        $notesOnly = array_keys($data) === ['instructor_notes']
            || (count($data) === 1 && array_key_exists('instructor_notes', $data));

        if (!$lesson->isScheduled()) {
            // Allow private instructor notes on completed lessons; nothing else.
            if (!($notesOnly && $lesson->status === Lesson::STATUS_COMPLETED)) {
                throw new BadRequestHttpException('Only scheduled lessons can be edited.');
            }
            $lesson->instructor_notes = $this->nullableText($data['instructor_notes']);
            $lesson->updated_at = gmdate('Y-m-d H:i:s');
            if (!$lesson->save(true, ['instructor_notes', 'updated_at'])) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }
            $lesson->refresh();

            return $this->toApiArray($lesson, $org);
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

        if (array_key_exists('service_id', $data) || array_key_exists('duration_minutes', $data)) {
            $resolved = $this->resolveServiceAndDuration($org, array_merge([
                'service_id' => $lesson->service_id,
                'duration_minutes' => $lesson->duration_minutes,
            ], $data));
            $lesson->service_id = $resolved['service_id'];
            $lesson->duration_minutes = $resolved['duration_minutes'];
        }

        if (array_key_exists('pickup_address', $data) || array_key_exists('pickup_location_id', $data)) {
            $this->applyPickupFields($lesson, $learner, $data, Lesson::PICKUP_BY_INSTRUCTOR);
        }

        if (array_key_exists('focus_tags', $data)) {
            $this->applyFocusTags($lesson, $data['focus_tags']);
        }

        if (array_key_exists('instructor_notes', $data)) {
            $lesson->instructor_notes = $this->nullableText($data['instructor_notes']);
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
     * Instructor clears the pickup-changed alert on the diary panel.
     *
     * @return array<string, mixed>
     */
    public function acknowledgePickupChange(int $id): array
    {
        $org = $this->requireOrganisation();
        $lesson = $this->findOwned($id);
        $lesson->pickup_change_acked_at = gmdate('Y-m-d H:i:s');
        $lesson->updated_at = $lesson->pickup_change_acked_at;
        if (!$lesson->save(false, ['pickup_change_acked_at', 'updated_at'])) {
            throw new BadRequestHttpException('Could not acknowledge pickup change.');
        }

        return $this->toApiArray($lesson, $org);
    }

    /**
     * Pupil updates pickup on their own lesson (sets change alert for instructor).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updatePickupAsLearner(int $id, int $learnerId, array $data): array
    {
        $lesson = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['id' => $id])
            ->one();
        if (!$lesson instanceof Lesson || (int) $lesson->learner_id !== $learnerId) {
            throw new NotFoundHttpException('Lesson not found.');
        }
        if (!$lesson->isScheduled()) {
            throw new BadRequestHttpException('Only upcoming lessons can change pickup.');
        }

        $learner = Learner::findOne(['id' => $learnerId]);
        if (!$learner instanceof Learner) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        $org = Organisation::findOne(['id' => (int) $lesson->organisation_id]);
        if ($org === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        $before = (string) ($lesson->pickup_address ?? '');
        $this->applyPickupFields($lesson, $learner, $data, Lesson::PICKUP_BY_LEARNER, forceChangeFlag: true);
        $after = (string) ($lesson->pickup_address ?? '');
        if ($before !== $after) {
            $lesson->pickup_changed_at = gmdate('Y-m-d H:i:s');
            $lesson->pickup_change_acked_at = null;
            (new LearnerProfileChangeService())->record(
                $learner,
                'portal_lesson_pickup',
                'Changed lesson pickup',
                [['field' => 'pickup', 'from' => $before !== '' ? $before : null, 'to' => $after !== '' ? $after : null]],
            );
        }
        $lesson->updated_at = gmdate('Y-m-d H:i:s');
        if (!$lesson->save()) {
            throw new BadRequestHttpException($this->firstError($lesson));
        }

        return [
            'id' => (int) $lesson->id,
            'pickup_address' => $lesson->pickup_address,
            'pickup_location_id' => $lesson->pickup_location_id !== null ? (int) $lesson->pickup_location_id : null,
            'pickup_changed' => $lesson->pickupChangePending(),
        ];
    }

    /**
     * Pupil suggests this-lesson focus tags (merged with any existing).
     *
     * @param list<mixed>|array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateFocusTagsAsLearner(int $id, int $learnerId, array $data): array
    {
        $lesson = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['id' => $id])
            ->one();
        if (!$lesson instanceof Lesson || (int) $lesson->learner_id !== $learnerId) {
            throw new NotFoundHttpException('Lesson not found.');
        }
        if (!$lesson->isScheduled()) {
            throw new BadRequestHttpException('Only upcoming lessons can suggest focus.');
        }

        $incoming = $data['focus_tags'] ?? $data;
        if (!is_array($incoming)) {
            throw new BadRequestHttpException('focus_tags must be a list.');
        }
        $merged = array_merge($lesson->focusTags(), $incoming);
        $this->applyFocusTags($lesson, $merged);
        $lesson->updated_at = gmdate('Y-m-d H:i:s');
        if (!$lesson->save(true, ['focus_tags_json', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($lesson));
        }

        return [
            'id' => (int) $lesson->id,
            'focus_tags' => $lesson->focusTags(),
        ];
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
        $lesson->cancelled_by = Lesson::CANCELLED_BY_INSTRUCTOR;
        $reason = trim((string) ($data['cancellation_reason'] ?? $data['reason'] ?? ''));
        if ($reason !== '') {
            $lesson->cancellation_reason = mb_substr($reason, 0, 500);
        }
        $policy = new CancellationPolicyService();
        $noticeHours = $policy->noticeHoursFromPayload($data);
        if ($noticeHours !== null) {
            $lesson->cancellation_notice_hours = $noticeHours;
            // Recording pupil notice means they cancelled (instructor is logging it).
            $lesson->cancelled_by = Lesson::CANCELLED_BY_LEARNER;
        }

        $finance = null;
        $tx = Yii::$app->db->beginTransaction();
        try {
            $attrs = ['status', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'updated_at'];
            if ($noticeHours !== null) {
                $attrs[] = 'cancellation_notice_hours';
            }
            if (!$lesson->save(true, $attrs)) {
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
     * Apply or waive charge on an already-cancelled lesson (e.g. pupil late cancel).
     *
     * @param array<string, mixed> $data charge: waived|outstanding|package
     * @return array<string, mixed>
     */
    public function settleCancellation(int $id, array $data = []): array
    {
        $org = $this->requireOrganisation();
        $lesson = $this->findOwned($id);

        if ($lesson->status !== Lesson::STATUS_CANCELLED) {
            throw new BadRequestHttpException('Only cancelled lessons can be settled this way.');
        }
        if ($lesson->settlement !== null && $lesson->settlement !== '') {
            throw new BadRequestHttpException('This cancellation has already been settled.');
        }

        $charge = strtolower(trim((string) ($data['charge'] ?? '')));
        if (!in_array($charge, ['waived', 'outstanding', 'package'], true)) {
            throw new BadRequestHttpException('Choose whether to charge or waive this cancellation.');
        }

        $finance = (new FinanceService())->settleCancelledLesson($lesson, $org, ['charge' => $charge]);
        if ($finance === null) {
            throw new BadRequestHttpException('Could not settle this cancellation.');
        }

        $lesson->refresh();
        $lesson->populateRelation('learner', $lesson->learner);
        $lesson->populateRelation('instructor', $lesson->instructor);

        $payload = $this->toApiArray($lesson, $org);
        $payload['finance'] = $finance;
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
        try {
            $serialized = (new WeatherService())->annotateLessons(
                $serialized,
                $dayStartLocal->format('Y-m-d'),
                (string) $org->timezone,
            );
        } catch (\Throwable $e) {
            Yii::error('Today weather annotate failed: ' . $e->getMessage(), __METHOD__);
        }

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
     * Resolve catalogue service + duration for create/update.
     *
     * @param array<string, mixed> $data
     * @return array{service_id: int|null, duration_minutes: int}
     */
    public function resolveServiceAndDuration(Organisation $org, array $data): array
    {
        $service = null;
        if (array_key_exists('service_id', $data) && $data['service_id'] !== null && $data['service_id'] !== '') {
            $service = OrganisationService::findOne([
                'id' => (int) $data['service_id'],
                'organisation_id' => (int) $org->id,
            ]);
            if ($service === null) {
                throw new BadRequestHttpException('That service was not found.');
            }
            if ($service->status === OrganisationService::STATUS_INACTIVE) {
                throw new BadRequestHttpException('That service is inactive.');
            }
        }

        if ($service !== null && !array_key_exists('duration_minutes', $data)) {
            $duration = $this->resolveDuration($service->duration_minutes);
        } elseif (array_key_exists('duration_minutes', $data)) {
            $duration = $this->resolveDuration($data['duration_minutes']);
        } elseif ($service !== null) {
            $duration = $this->resolveDuration($service->duration_minutes);
        } else {
            // Prefer default active catalogue service when nothing specified.
            $service = OrganisationService::find()
                ->andWhere([
                    'organisation_id' => (int) $org->id,
                    'status' => OrganisationService::STATUS_ACTIVE,
                    'is_default' => true,
                ])
                ->one();
            $duration = $service !== null
                ? $this->resolveDuration($service->duration_minutes)
                : $this->resolveDuration(null);
        }

        return [
            'service_id' => $service !== null ? (int) $service->id : null,
            'duration_minutes' => $duration,
        ];
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
     * Apply saved place and/or freeform pickup onto a lesson.
     *
     * @param array<string, mixed> $data
     */
    private function applyPickupFields(
        Lesson $lesson,
        Learner $learner,
        array $data,
        string $setBy,
        bool $forceChangeFlag = false,
    ): void {
        $hasLocationId = array_key_exists('pickup_location_id', $data);
        $hasAddress = array_key_exists('pickup_address', $data);
        $locationIdRaw = $hasLocationId ? $data['pickup_location_id'] : null;

        if ($hasLocationId && $locationIdRaw !== null && $locationIdRaw !== '') {
            $locationId = (int) $locationIdRaw;
            $location = LearnerLocation::findOne([
                'id' => $locationId,
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
            ]);
            if (!$location instanceof LearnerLocation) {
                throw new BadRequestHttpException('Pickup place not found for this pupil.');
            }
            $lesson->pickup_location_id = (int) $location->id;
            $lesson->pickup_address = $location->address;
            $lesson->pickup_set_by = $setBy;
            if ($forceChangeFlag) {
                // Caller sets changed timestamps when address actually changes.
            }

            return;
        }

        if ($hasLocationId && ($locationIdRaw === null || $locationIdRaw === '')) {
            $lesson->pickup_location_id = null;
        }

        if ($hasAddress || !$hasLocationId) {
            $allowEmpty = $hasAddress;
            $address = $this->resolvePickup(
                $hasAddress ? $data['pickup_address'] : null,
                $learner,
                allowEmpty: $allowEmpty,
            );
            // Prefer default saved place when no freeform / location was supplied.
            if (!$hasAddress && !$hasLocationId) {
                $default = LearnerLocation::find()
                    ->andWhere([
                        'organisation_id' => (int) $learner->organisation_id,
                        'learner_id' => (int) $learner->id,
                        'is_default' => true,
                    ])
                    ->one();
                if ($default instanceof LearnerLocation) {
                    $lesson->pickup_location_id = (int) $default->id;
                    $lesson->pickup_address = $default->address;
                    $lesson->pickup_set_by = Lesson::PICKUP_BY_SYSTEM;

                    return;
                }
            }
            $lesson->pickup_address = $address;
            if ($hasAddress && trim((string) ($data['pickup_address'] ?? '')) !== '') {
                // Custom freeform — detach from saved place unless location_id also sent.
                if (!$hasLocationId || $locationIdRaw === null || $locationIdRaw === '') {
                    $lesson->pickup_location_id = null;
                }
            }
            $lesson->pickup_set_by = $setBy;
        }
    }

    /**
     * @param list<mixed>|string|null $tags
     */
    private function applyFocusTags(Lesson $lesson, mixed $tags): void
    {
        if ($tags === null) {
            return;
        }
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : (preg_split('/[,\\n]+/', $tags) ?: []);
        }
        if (!is_array($tags)) {
            throw new BadRequestHttpException('focus_tags must be a list of short labels.');
        }
        $clean = [];
        foreach ($tags as $tag) {
            if (!is_string($tag) && !is_numeric($tag)) {
                continue;
            }
            $text = trim((string) $tag);
            if ($text === '') {
                continue;
            }
            $clean[] = mb_substr($text, 0, 48);
            if (count($clean) >= 12) {
                break;
            }
        }
        $lesson->focus_tags_json = $clean === [] ? null : json_encode(array_values(array_unique($clean)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializePickupLocation(Lesson $lesson): ?array
    {
        $loc = $lesson->pickupLocation;
        if (!$loc instanceof LearnerLocation) {
            return null;
        }

        return [
            'id' => (int) $loc->id,
            'label' => $loc->label,
            'icon' => $loc->icon,
            'address' => $loc->address,
            'is_default' => (bool) $loc->is_default,
        ];
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
            'learner_transmission' => $learner?->transmission,
            'starts_at' => $startsUtc->format(DATE_ATOM),
            'starts_at_local' => OrganisationTime::formatLocalIso($local),
            'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
            'starts_at_time' => $local->format('H:i'),
            'ends_at' => $endsUtc->format(DATE_ATOM),
            'ends_at_local' => OrganisationTime::formatLocalIso($endsLocal),
            'ends_at_time' => $endsLocal->format('H:i'),
            'timezone' => $organisation->timezone,
            'duration_minutes' => (int) $lesson->duration_minutes,
            'service_id' => $lesson->service_id !== null ? (int) $lesson->service_id : null,
            'pickup_address' => $lesson->pickup_address,
            'pickup_location_id' => $lesson->pickup_location_id !== null ? (int) $lesson->pickup_location_id : null,
            'pickup_set_by' => $lesson->pickup_set_by,
            'pickup_changed' => $lesson->pickupChangePending(),
            'pickup_location' => $this->serializePickupLocation($lesson),
            'focus_tags' => $lesson->focusTags(),
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
            'cancelled_by' => $lesson->cancelled_by,
            'cancellation_reason' => $lesson->cancellation_reason,
            'cancellation_notice_hours' => $lesson->cancellation_notice_hours !== null
                ? (int) $lesson->cancellation_notice_hours
                : null,
            'cancellation_notice_label' => (new CancellationPolicyService())->formatNoticeLabel(
                $lesson->cancellation_notice_hours !== null ? (int) $lesson->cancellation_notice_hours : null,
            ),
            'needs_cancellation_settlement' => $lesson->status === Lesson::STATUS_CANCELLED
                && ($lesson->settlement === null || $lesson->settlement === ''),
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
