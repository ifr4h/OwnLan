<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\Lesson;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Deterministic Lesson Context for the Diary selected-lesson panel.
 *
 * Composes existing services. No AI. No new admin fields.
 * Facts are ranked and capped — omission beats weak claims.
 */
class LessonContextService
{
    public const MAX_FACTS = 3;

    /** Days since last completed lesson before “returning after a break”. */
    public const RETURN_GAP_DAYS = 21;

    /** Pickup history samples required before claiming “usual”. */
    public const MIN_PICKUP_SAMPLES = 3;

    public const MIN_PICKUP_SHARE = 0.6;

    private LessonService $lessons;
    private FinanceService $finance;
    private ContinuityService $continuity;
    private BookingSuggestionService $suggestions;
    private TravelFeasibilityService $travel;

    public function __construct(
        ?LessonService $lessons = null,
        ?FinanceService $finance = null,
        ?ContinuityService $continuity = null,
        ?BookingSuggestionService $suggestions = null,
        ?TravelFeasibilityService $travel = null,
    ) {
        $this->lessons = $lessons ?? new LessonService();
        $this->finance = $finance ?? new FinanceService();
        $this->continuity = $continuity ?? new ContinuityService();
        $this->suggestions = $suggestions ?? new BookingSuggestionService();
        $this->travel = $travel ?? new TravelFeasibilityService();
    }

    /**
     * @return array<string, mixed>
     */
    public function forLesson(int $id): array
    {
        $org = $this->requireOrganisation();
        $lessonRow = $this->lessons->get($id);
        $learnerId = (int) $lessonRow['learner_id'];
        $learner = $this->findLearnerOwned($learnerId);

        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $startsUtc = new DateTimeImmutable((string) $lessonRow['starts_at']);
        $endsUtc = new DateTimeImmutable((string) $lessonRow['ends_at']);
        $duration = (int) $lessonRow['duration_minutes'];
        $status = (string) $lessonRow['status'];

        $travelToNext = $this->travelForLesson($id, $org);
        if ($travelToNext !== null) {
            $lessonRow['travel_to_next'] = $travelToNext;
        }

        $snapshot = $this->finance->compactSnapshot($learnerId);
        $creditMinutes = (int) ($snapshot['credit_minutes'] ?? 0);
        $creditCovers = $status === Lesson::STATUS_SCHEDULED
            && $creditMinutes >= $duration
            && $duration > 0;

        $previousCompleted = $this->previousCompleted($learnerId, (string) $lessonRow['starts_at'], $org);
        $nextScheduled = $this->nextScheduledAfter($learnerId, (string) $lessonRow['starts_at'], $org);
        $history = $this->historyBlock($learnerId, $org);
        $pickup = $this->pickupBlock($lessonRow, $learnerId, $learner);
        $suggestion = $this->suggestions->suggestForLearner($learnerId);
        $continuity = $this->continuity->contextForLearner($learnerId);

        $teaching = [
            'learner_next_focus' => $lessonRow['learner_next_focus'] ?? null,
            'learner_last_lesson_summary' => $lessonRow['learner_last_lesson_summary'] ?? null,
            'instructor_notes' => $lessonRow['instructor_notes'] ?? null,
            'lesson_next_focus' => $lessonRow['next_focus'] ?? null,
            'lesson_learner_summary' => $lessonRow['learner_summary'] ?? null,
            'previous_completed' => $previousCompleted,
            'focus_tags' => $lessonRow['focus_tags'] ?? [],
            'messages' => (new LessonMessageService())->previewForLesson($id),
        ];

        $financeBlock = [
            'snapshot' => $snapshot,
            'lesson_settlement' => $lessonRow['settlement'] ?? null,
            'financial_line' => $lessonRow['financial_line'] ?? null,
            'credit_covers_duration' => $creditCovers,
            'credit_after_today_minutes' => $status === Lesson::STATUS_SCHEDULED && $creditCovers
                ? max(0, $creditMinutes - $duration)
                : null,
        ];

        $facts = $this->rankFacts([
            $this->factUnresolvedPast($status, $endsUtc, $nowUtc),
            $this->factCancellationSettle($lessonRow),
            $this->factCancellationNotice($lessonRow),
            $this->factTravel($travelToNext),
            $this->factPickupChanged($pickup),
            $this->factReturning($previousCompleted, $startsUtc, $org),
            $this->factOutstanding($lessonRow, $snapshot),
            $this->factCredit($status, $snapshot, $creditCovers, $financeBlock['credit_after_today_minutes']),
            $this->factTest($lessonRow['test_journey'] ?? null),
            $this->factNextBooking($nextScheduled),
            $this->factContinuity($continuity, $nextScheduled),
            $this->factPattern($suggestion),
            $this->factHistoryTotals($history),
        ]);

        return [
            'lesson' => $lessonRow,
            'pupil' => [
                'id' => (int) $learner->id,
                'name' => $learner->fullName,
                'first_name' => $learner->first_name,
                'initials' => $this->initials($learner),
                'mobile' => $learner->mobile,
                'transmission' => $learner->transmission,
            ],
            'teaching' => $teaching,
            'finance' => $financeBlock,
            'continuity' => $continuity,
            'test_journey' => $lessonRow['test_journey'] ?? null,
            'travel_to_next' => $travelToNext,
            'booking_suggestion' => $suggestion,
            'next_scheduled' => $nextScheduled,
            'history' => $history,
            'pickup' => $pickup,
            'facts' => $facts,
            'actions' => $this->actions($lessonRow, $snapshot, $endsUtc, $nowUtc),
            'panel_phase' => $this->panelPhase($status, $startsUtc, $endsUtc, $nowUtc),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function travelForLesson(int $lessonId, Organisation $org): ?array
    {
        $lesson = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['id' => $lessonId])
            ->one();
        if (!$lesson instanceof Lesson || $lesson->status !== Lesson::STATUS_SCHEDULED) {
            return null;
        }

        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
        $dayStart = $local->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');
        $fromUtc = OrganisationTime::formatUtc(
            $dayStart->setTimezone(new DateTimeZone('UTC')),
        );
        $toUtc = OrganisationTime::formatUtc(
            $dayEnd->setTimezone(new DateTimeZone('UTC')),
        );

        /** @var Lesson[] $dayLessons */
        $dayLessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $fromUtc])
            ->andWhere(['<', 'starts_at', $toUtc])
            ->with(['learner'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $slim = [];
        foreach ($dayLessons as $row) {
            $startUtc = new DateTimeImmutable($row->starts_at, new DateTimeZone('UTC'));
            $endUtc = $startUtc->modify('+' . (int) $row->duration_minutes . ' minutes');
            $slim[] = [
                'id' => (int) $row->id,
                'status' => $row->status,
                'starts_at' => $startUtc->format(DATE_ATOM),
                'ends_at' => $endUtc->format(DATE_ATOM),
                'duration_minutes' => (int) $row->duration_minutes,
                'pickup_address' => $row->pickup_address,
                'learner_name' => $row->learner?->fullName,
            ];
        }

        $annotated = $this->travel->annotateDiaryItems($slim);
        foreach ($annotated as $item) {
            if ((int) ($item['id'] ?? 0) === $lessonId) {
                return $item['travel_to_next'] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function previousCompleted(int $learnerId, string $beforeStartsAt, Organisation $org): ?array
    {
        /** @var Lesson|null $lesson */
        $lesson = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_COMPLETED,
            ])
            ->andWhere(['<', 'starts_at', $beforeStartsAt])
            ->orderBy(['starts_at' => SORT_DESC])
            ->one();

        if (!$lesson instanceof Lesson) {
            return null;
        }

        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);

        return [
            'id' => (int) $lesson->id,
            'starts_at' => (new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC')))->format(DATE_ATOM),
            'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
            'starts_at_day' => $local->format('j F'),
            'duration_minutes' => (int) $lesson->duration_minutes,
            'learner_summary' => $lesson->learner_summary,
            'next_focus' => $lesson->next_focus,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextScheduledAfter(int $learnerId, string $afterStartsAt, Organisation $org): ?array
    {
        /** @var Lesson|null $lesson */
        $lesson = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>', 'starts_at', $afterStartsAt])
            ->with(['learner'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->one();

        if (!$lesson instanceof Lesson) {
            return null;
        }

        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
        $ends = $local->modify('+' . (int) $lesson->duration_minutes . ' minutes');

        return [
            'id' => (int) $lesson->id,
            'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
            'starts_at_local' => OrganisationTime::formatLocalIso($local),
            'starts_at_time' => $local->format('H:i'),
            'ends_at_time' => $ends->format('H:i'),
            'duration_minutes' => (int) $lesson->duration_minutes,
            'day_label' => $local->format('j M'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyBlock(int $learnerId, Organisation $org): array
    {
        /** @var Lesson[] $completed */
        $completed = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_COMPLETED,
            ])
            ->orderBy(['starts_at' => SORT_DESC])
            ->all();

        $totalMinutes = 0;
        foreach ($completed as $lesson) {
            $totalMinutes += (int) $lesson->duration_minutes;
        }

        $recent = [];
        foreach (array_slice($completed, 0, 3) as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $recent[] = [
                'id' => (int) $lesson->id,
                'day_label' => $local->format('j M'),
                'duration_minutes' => (int) $lesson->duration_minutes,
            ];
        }

        /** @var Lesson[] $future */
        $future = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>=', 'starts_at', (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s')])
            ->orderBy(['starts_at' => SORT_ASC])
            ->limit(3)
            ->all();

        $comingUp = [];
        foreach ($future as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $comingUp[] = [
                'id' => (int) $lesson->id,
                'day_label' => $local->format('j M'),
                'starts_at_time' => $local->format('H:i'),
                'duration_minutes' => (int) $lesson->duration_minutes,
            ];
        }

        $first = null;
        if ($completed !== []) {
            $oldest = $completed[count($completed) - 1];
            $firstLocal = OrganisationTime::utcToLocal($oldest->starts_at, $org);
            $first = [
                'id' => (int) $oldest->id,
                'day_label' => $firstLocal->format('j F Y'),
            ];
        }

        $syllabus = (new ProgressService())->syllabusCoverage($learnerId, (int) $org->id);

        return [
            'completed_count' => count($completed),
            'teaching_minutes' => $totalMinutes,
            'teaching_hours_label' => $this->hoursLabel($totalMinutes),
            'syllabus_percent' => $syllabus['percent'],
            'syllabus_line' => $syllabus['line'],
            'recent_completed' => $recent,
            'coming_up' => $comingUp,
            'first_lesson' => $first,
        ];
    }

    /**
     * @param array<string, mixed> $lessonRow
     * @return array<string, mixed>
     */
    private function pickupBlock(array $lessonRow, int $learnerId, Learner $learner): array
    {
        $current = trim((string) ($lessonRow['pickup_address'] ?? ''));
        $current = $current !== '' ? $current : null;

        /** @var Lesson[] $history */
        $history = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['learner_id' => $learnerId])
            ->andWhere(['!=', 'status', Lesson::STATUS_CANCELLED])
            ->andWhere(['!=', 'id', (int) $lessonRow['id']])
            ->orderBy(['starts_at' => SORT_DESC])
            ->limit(8)
            ->all();

        $addresses = [];
        foreach ($history as $row) {
            $text = trim((string) ($row->pickup_address ?? ''));
            if ($text !== '') {
                $addresses[] = $text;
            }
        }

        $usual = $this->clearModeString($addresses);
        if ($usual === null) {
            $default = trim((string) ($learner->default_pickup_address ?? ''));
            $usual = $default !== '' ? $default : null;
        }

        $learnerFlagged = !empty($lessonRow['pickup_changed']);
        $unusual = $current !== null
            && $usual !== null
            && !$this->samePickup($current, $usual);

        return [
            'address' => $current,
            'usual_address' => $usual,
            'changed' => $learnerFlagged || $unusual,
            'learner_changed' => $learnerFlagged,
            'location' => is_array($lessonRow['pickup_location'] ?? null) ? $lessonRow['pickup_location'] : null,
            'set_by' => $lessonRow['pickup_set_by'] ?? null,
        ];
    }

    /**
     * @param list<array<string, mixed>|null> $candidates
     * @return list<array<string, mixed>>
     */
    private function rankFacts(array $candidates): array
    {
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        $facts = [];
        foreach ($candidates as $fact) {
            if ($fact === null) {
                continue;
            }
            $facts[] = $fact;
        }

        usort($facts, static function (array $a, array $b) use ($priorityOrder): int {
            $pa = $priorityOrder[$a['priority']] ?? 9;
            $pb = $priorityOrder[$b['priority']] ?? 9;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        });

        return array_slice($facts, 0, self::MAX_FACTS);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function factUnresolvedPast(string $status, DateTimeImmutable $endsUtc, DateTimeImmutable $nowUtc): ?array
    {
        if ($status !== Lesson::STATUS_SCHEDULED || $endsUtc > $nowUtc) {
            return null;
        }

        return [
            'id' => 'unresolved_past',
            'priority' => 'high',
            'text' => 'This lesson hasn\'t been closed yet.',
            'slot' => 'status',
        ];
    }

    /**
     * @param array<string, mixed> $lessonRow
     * @return array<string, mixed>|null
     */
    private function factCancellationSettle(array $lessonRow): ?array
    {
        if (($lessonRow['status'] ?? null) !== Lesson::STATUS_CANCELLED) {
            return null;
        }
        if (empty($lessonRow['needs_cancellation_settlement'])) {
            return null;
        }

        $who = ($lessonRow['cancelled_by'] ?? null) === Lesson::CANCELLED_BY_LEARNER
            ? 'Pupil cancelled'
            : 'Cancelled';
        $reason = trim((string) ($lessonRow['cancellation_reason'] ?? ''));
        $notice = trim((string) ($lessonRow['cancellation_notice_label'] ?? ''));
        $text = $who . ' · decide whether to charge';
        if ($notice !== '') {
            $text = $who . ' with ' . $notice . ' · decide whether to charge';
        }
        if ($reason !== '') {
            $text .= ' · “' . mb_substr($reason, 0, 80) . '”';
        }

        return [
            'id' => 'cancellation_settle',
            'priority' => 'high',
            'text' => $text,
            'slot' => 'status',
        ];
    }

    /**
     * @param array<string, mixed> $lessonRow
     * @return array<string, mixed>|null
     */
    private function factCancellationNotice(array $lessonRow): ?array
    {
        if (($lessonRow['status'] ?? null) !== Lesson::STATUS_CANCELLED) {
            return null;
        }
        if (!empty($lessonRow['needs_cancellation_settlement'])) {
            return null;
        }
        $notice = trim((string) ($lessonRow['cancellation_notice_label'] ?? ''));
        if ($notice === '') {
            return null;
        }
        $who = ($lessonRow['cancelled_by'] ?? null) === Lesson::CANCELLED_BY_LEARNER
            ? 'Pupil cancelled'
            : 'Cancelled';

        return [
            'id' => 'cancellation_notice',
            'priority' => 'medium',
            'text' => $who . ' with ' . $notice,
            'slot' => 'status',
        ];
    }

    /**
     * @param array<string, mixed>|null $travel
     * @return array<string, mixed>|null
     */
    private function factTravel(?array $travel): ?array
    {
        if ($travel === null || empty($travel['is_warning'])) {
            return null;
        }

        $mins = (int) ($travel['travel_minutes'] ?? 0);
        $available = (int) ($travel['available_minutes'] ?? 0);
        $text = (string) ($travel['message'] ?? '');
        if ($text === '' && $mins > 0) {
            $text = 'Only ' . $available . ' min from previous lesson. Typical journey: ' . $mins . ' min';
        }
        if ($text === '') {
            return null;
        }

        return [
            'id' => 'travel_tight',
            'priority' => 'high',
            'text' => $text,
            'slot' => 'travel',
        ];
    }

    /**
     * @param array<string, mixed> $pickup
     * @return array<string, mixed>|null
     */
    private function factPickupChanged(array $pickup): ?array
    {
        if (empty($pickup['changed'])) {
            return null;
        }

        return [
            'id' => 'pickup_changed',
            'priority' => 'high',
            'text' => 'Pickup changed for this lesson',
            'slot' => 'pickup',
        ];
    }

    /**
     * @param array<string, mixed>|null $previous
     * @return array<string, mixed>|null
     */
    private function factReturning(?array $previous, DateTimeImmutable $startsUtc, Organisation $org): ?array
    {
        if ($previous === null) {
            return null;
        }

        $prevUtc = new DateTimeImmutable((string) $previous['starts_at']);
        $prevLocal = $prevUtc->setTimezone(OrganisationTime::timezoneFor($org))->setTime(0, 0, 0);
        $thisLocal = $startsUtc->setTimezone(OrganisationTime::timezoneFor($org))->setTime(0, 0, 0);
        $days = (int) $prevLocal->diff($thisLocal)->format('%a');
        if ($days < self::RETURN_GAP_DAYS) {
            return null;
        }

        $weeks = (int) floor($days / 7);
        $text = $weeks >= 2
            ? 'First lesson in ' . $weeks . ' weeks'
            : 'First lesson in ' . $days . ' days';

        return [
            'id' => 'returning',
            'priority' => 'high',
            'text' => $text,
            'slot' => 'continuity',
        ];
    }

    /**
     * @param array<string, mixed> $lessonRow
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>|null
     */
    private function factOutstanding(array $lessonRow, array $snapshot): ?array
    {
        if (($lessonRow['settlement'] ?? null) === FinanceService::SETTLEMENT_OUTSTANDING
            && (int) ($lessonRow['price_pence'] ?? 0) > 0) {
            $pounds = (int) round(((int) $lessonRow['price_pence']) / 100);

            return [
                'id' => 'outstanding',
                'priority' => 'high',
                'text' => '£' . $pounds . ' due',
                'slot' => 'money',
            ];
        }

        if (!empty($snapshot['owes_money']) && !empty($snapshot['amount_owed_label'])) {
            return [
                'id' => 'owes_money',
                'priority' => 'medium',
                'text' => (string) $snapshot['amount_owed_label'],
                'slot' => 'money',
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>|null
     */
    private function factCredit(
        string $status,
        array $snapshot,
        bool $creditCovers,
        ?int $afterTodayMinutes,
    ): ?array {
        if ($status !== Lesson::STATUS_SCHEDULED) {
            return null;
        }
        $credit = (int) ($snapshot['credit_minutes'] ?? 0);
        if ($credit <= 0) {
            return null;
        }

        if ($creditCovers && $afterTodayMinutes !== null) {
            if ($afterTodayMinutes <= 0) {
                return [
                    'id' => 'credit',
                    'priority' => 'medium',
                    'text' => 'Covered by lesson credit · none left after today',
                    'slot' => 'money',
                ];
            }

            return [
                'id' => 'credit',
                'priority' => 'medium',
                'text' => 'Covered by lesson credit · ' . $this->remainingPhrase($afterTodayMinutes) . ' after today',
                'slot' => 'money',
            ];
        }

        $line = (string) ($snapshot['credit_remaining_line'] ?? '');
        if ($line === '') {
            return null;
        }

        return [
            'id' => 'credit',
            'priority' => 'medium',
            'text' => $line,
            'slot' => 'money',
        ];
    }

    /**
     * @param array<string, mixed>|null $test
     * @return array<string, mixed>|null
     */
    private function factTest(?array $test): ?array
    {
        if ($test === null || empty($test['countdown_label'])) {
            return null;
        }

        $parts = [(string) $test['countdown_label']];
        if (isset($test['lessons_booked_before_test']) && is_int($test['lessons_booked_before_test'])) {
            $n = $test['lessons_booked_before_test'];
            $parts[] = $n === 1
                ? '1 lesson booked before test'
                : $n . ' lessons booked before test';
        }

        return [
            'id' => 'test',
            'priority' => 'medium',
            'text' => implode(' · ', $parts),
            'slot' => 'test',
        ];
    }

    /**
     * @param array<string, mixed>|null $next
     * @return array<string, mixed>|null
     */
    private function factNextBooking(?array $next): ?array
    {
        if ($next === null) {
            return null;
        }

        return [
            'id' => 'next_booking',
            'priority' => 'medium',
            'text' => 'Next · ' . $next['day_label'] . ' · ' . $next['starts_at_time'],
            'slot' => 'next',
            'lesson_id' => $next['id'],
        ];
    }

    /**
     * @param array<string, mixed>|null $continuity
     * @param array<string, mixed>|null $next
     * @return array<string, mixed>|null
     */
    private function factContinuity(?array $continuity, ?array $next): ?array
    {
        if ($next !== null || $continuity === null) {
            return null;
        }
        if (empty($continuity['usual_cadence']) && empty($continuity['needs_attention'])) {
            return null;
        }

        $parts = [];
        if (!empty($continuity['usual_cadence'])) {
            $parts[] = 'Usually ' . $continuity['usual_cadence'];
        }
        if (!empty($continuity['detail'])) {
            $parts[] = (string) $continuity['detail'];
        }
        if ($parts === []) {
            return null;
        }

        return [
            'id' => 'continuity',
            'priority' => 'medium',
            'text' => implode(' · ', $parts),
            'slot' => 'continuity',
        ];
    }

    /**
     * @param array<string, mixed> $suggestion
     * @return array<string, mixed>|null
     */
    private function factPattern(array $suggestion): ?array
    {
        $pattern = $suggestion['schedule_pattern'] ?? null;
        if (!is_array($pattern) || empty($pattern['weekday']) || empty($pattern['time'])) {
            return null;
        }
        if (($suggestion['schedule_source'] ?? '') !== 'pattern') {
            return null;
        }

        return [
            'id' => 'usual_slot',
            'priority' => 'low',
            'text' => 'Usually ' . $pattern['weekday'] . 's around ' . $pattern['time'],
            'slot' => 'continuity',
        ];
    }

    /**
     * @param array<string, mixed> $history
     * @return array<string, mixed>|null
     */
    private function factHistoryTotals(array $history): ?array
    {
        $count = (int) ($history['completed_count'] ?? 0);
        if ($count < 4) {
            return null;
        }

        $hours = (string) ($history['teaching_hours_label'] ?? '');
        $text = $count . ' lessons together';
        if ($hours !== '') {
            $text .= ' · ' . $hours;
        }

        return [
            'id' => 'history_totals',
            'priority' => 'low',
            'text' => $text,
            'slot' => 'continuity',
        ];
    }

    /**
     * @param array<string, mixed> $lessonRow
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function actions(
        array $lessonRow,
        array $snapshot,
        DateTimeImmutable $endsUtc,
        DateTimeImmutable $nowUtc,
    ): array {
        $status = (string) $lessonRow['status'];
        $primary = [];
        $secondary = [];
        $overflow = [];

        if ($status === Lesson::STATUS_SCHEDULED) {
            $unresolved = $endsUtc <= $nowUtc;
            if ($unresolved || !empty($lessonRow['can_complete'])) {
                $primary[] = ['id' => 'complete', 'label' => 'Complete lesson'];
            }
            if (!$unresolved) {
                $primary[] = ['id' => 'move', 'label' => 'Move'];
            }
            $secondary[] = ['id' => 'book_next', 'label' => 'Book next'];
            if (!empty($lessonRow['can_mark_no_show'])) {
                $overflow[] = ['id' => 'no_show', 'label' => 'Mark no-show'];
            }
            $overflow[] = ['id' => 'cancel', 'label' => 'Cancel lesson'];
            $overflow[] = ['id' => 'remove', 'label' => 'Remove mistaken booking'];
        } elseif ($status === Lesson::STATUS_COMPLETED) {
            $primary[] = ['id' => 'recap', 'label' => 'View recap'];
            $secondary[] = ['id' => 'book_next', 'label' => 'Book next'];
        } elseif ($status === Lesson::STATUS_CANCELLED || $status === Lesson::STATUS_NO_SHOW) {
            $primary[] = ['id' => 'book_next', 'label' => 'Book another'];
        }

        $settlement = $lessonRow['settlement'] ?? null;
        $price = (int) ($lessonRow['price_pence'] ?? 0);
        $needsCancelSettle = !empty($lessonRow['needs_cancellation_settlement']);
        if ($needsCancelSettle && $status === Lesson::STATUS_CANCELLED) {
            array_unshift($primary, ['id' => 'decide_charge', 'label' => 'Decide charge']);
        } elseif ($settlement === FinanceService::SETTLEMENT_OUTSTANDING && $price > 0) {
            array_unshift($secondary, ['id' => 'pay', 'label' => 'Record payment']);
        } elseif (!empty($snapshot['owes_money']) && $status === Lesson::STATUS_COMPLETED) {
            array_unshift($secondary, ['id' => 'pay', 'label' => 'Record payment']);
        }

        $overflow[] = ['id' => 'open_pupil', 'label' => 'Open full pupil'];
        $overflow[] = ['id' => 'open_full', 'label' => 'Open full lesson'];

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'overflow' => $overflow,
        ];
    }

    private function panelPhase(
        string $status,
        DateTimeImmutable $startsUtc,
        DateTimeImmutable $endsUtc,
        DateTimeImmutable $nowUtc,
    ): string {
        if ($status === Lesson::STATUS_COMPLETED) {
            return 'completed';
        }
        if ($status === Lesson::STATUS_CANCELLED) {
            return 'cancelled';
        }
        if ($status === Lesson::STATUS_NO_SHOW) {
            return 'no_show';
        }
        if ($status === Lesson::STATUS_SCHEDULED && $endsUtc <= $nowUtc) {
            return 'unresolved';
        }
        if ($status === Lesson::STATUS_SCHEDULED && $startsUtc <= $nowUtc && $nowUtc < $endsUtc) {
            return 'now';
        }
        if ($status === Lesson::STATUS_SCHEDULED) {
            $hours = ($startsUtc->getTimestamp() - $nowUtc->getTimestamp()) / 3600;
            if ($hours <= 24) {
                return 'today';
            }

            return 'upcoming';
        }

        return 'upcoming';
    }

    /**
     * @param list<string> $values
     */
    private function clearModeString(array $values): ?string
    {
        if ($values === []) {
            return null;
        }
        $counts = [];
        foreach ($values as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        $modeKey = array_key_first($counts);
        $modeCount = $counts[$modeKey];
        $share = $modeCount / count($values);
        if ($modeCount < self::MIN_PICKUP_SAMPLES || $share < self::MIN_PICKUP_SHARE) {
            return null;
        }

        return (string) $modeKey;
    }

    private function samePickup(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    private function hoursLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($hours > 0 && $rem === 0) {
            return $hours === 1 ? '1 hour' : $hours . ' hours';
        }
        if ($hours > 0) {
            return $hours . 'h ' . $rem . 'm';
        }

        return $minutes . ' minutes';
    }

    private function remainingPhrase(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'none left';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($hours > 0 && $rem === 0) {
            return $hours === 1 ? '1h left' : $hours . 'h left';
        }
        if ($hours > 0) {
            return $hours . 'h ' . $rem . 'm left';
        }

        return $minutes . 'm left';
    }

    private function initials(Learner $learner): string
    {
        $first = mb_substr(trim((string) $learner->first_name), 0, 1);
        $last = mb_substr(trim((string) $learner->last_name), 0, 1);
        $out = mb_strtoupper($first . $last);

        return $out !== '' ? $out : '?';
    }

    /**
     * @throws UnauthorizedHttpException
     * @throws NotFoundHttpException
     */
    private function requireOrganisation(): Organisation
    {
        if (\Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }

        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findLearnerOwned(int $learnerId): Learner
    {
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId, 'archived_at' => null])
            ->one();
        if (!$learner instanceof Learner) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }
}
