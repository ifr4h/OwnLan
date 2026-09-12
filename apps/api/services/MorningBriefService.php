<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\Enquiry;
use app\models\LearnerIntake;
use app\models\Lesson;
use app\models\LessonBookingRequest;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Morning Brief V1 — prepare the working day without a widget dashboard.
 *
 * Only surfaces actions the system genuinely knows matter.
 */
class MorningBriefService
{
    public const MAX_NEEDS_YOU = 5;

    /** Look ahead for fillable cancelled slots. */
    public const EMPTY_SEAT_HORIZON_DAYS = 7;

    /** Test approaching + gap: only when within this many days. */
    public const TEST_GAP_HORIZON_DAYS = 28;

    private EmptySeatService $emptySeats;
    private ContinuityService $continuity;
    private TestJourneyService $tests;

    public function __construct(
        ?EmptySeatService $emptySeats = null,
        ?ContinuityService $continuity = null,
        ?TestJourneyService $tests = null,
    ) {
        $this->emptySeats = $emptySeats ?? new EmptySeatService();
        $this->continuity = $continuity ?? new ContinuityService();
        $this->tests = $tests ?? new TestJourneyService();
    }

    /**
     * @param list<array<string, mixed>> $dayLessons serialized today lessons (ordered)
     * @return array{lesson_count: int, window_start: string|null, window_end: string|null, window_label: string|null, line: string}
     */
    public function daySummary(array $dayLessons): array
    {
        $teachable = array_values(array_filter(
            $dayLessons,
            static fn (array $l) => ($l['status'] ?? null) === Lesson::STATUS_SCHEDULED
                || ($l['status'] ?? null) === Lesson::STATUS_COMPLETED,
        ));

        if ($teachable === []) {
            return [
                'lesson_count' => 0,
                'window_start' => null,
                'window_end' => null,
                'window_label' => null,
                'line' => 'No lessons today',
            ];
        }

        $count = count($teachable);
        $first = $teachable[0];
        $last = $teachable[count($teachable) - 1];
        $start = (string) ($first['starts_at_time'] ?? '');
        $end = (string) ($last['ends_at_time'] ?? '');
        $window = ($start !== '' && $end !== '') ? $start . '–' . $end : null;
        $noun = $count === 1 ? 'lesson' : 'lessons';

        return [
            'lesson_count' => $count,
            'window_start' => $start !== '' ? $start : null,
            'window_end' => $end !== '' ? $end : null,
            'window_label' => $window,
            'line' => $window !== null
                ? $count . ' ' . $noun . ' · ' . $window
                : $count . ' ' . $noun,
        ];
    }

    /**
     * Prioritised “Needs you” actions — empty seats, rebooking, test gaps.
     *
     * @return list<array<string, mixed>>
     */
    public function needsYou(
        Organisation $organisation,
        ?DateTimeImmutable $nowUtc = null,
    ): array {
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $actions = [];
        foreach ($this->bookingRequestActions($organisation, $nowUtc) as $action) {
            $actions[] = $action;
        }
        foreach ($this->emptySeatActions($organisation, $nowUtc) as $action) {
            $actions[] = $action;
        }
        $rebook = $this->rebookActions($nowUtc);
        $rebookLearnerIds = [];
        foreach ($rebook as $action) {
            $actions[] = $action;
            if ($action['learner_id'] !== null) {
                $rebookLearnerIds[(int) $action['learner_id']] = true;
            }
        }
        foreach ($this->testGapActions($organisation, $nowUtc, $rebookLearnerIds) as $action) {
            $actions[] = $action;
        }
        foreach ($this->testPrepActions($organisation, $nowUtc) as $action) {
            $actions[] = $action;
        }
        foreach ($this->profileChangeActions() as $action) {
            $actions[] = $action;
        }
        foreach ($this->intakeReviewActions() as $action) {
            $actions[] = $action;
        }
        foreach ($this->enquiryReviewActions() as $action) {
            $actions[] = $action;
        }

        usort($actions, static function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }

            return strcmp((string) $a['title'], (string) $b['title']);
        });

        return array_slice($actions, 0, self::MAX_NEEDS_YOU);
    }

    /**
     * Pending learner booking requests.
     *
     * @return list<array<string, mixed>>
     */
    private function bookingRequestActions(Organisation $organisation, DateTimeImmutable $nowUtc): array
    {
        $orgId = TenantContext::requireOrganisationId();
        (new LessonBookingRequestService())->expireStaleForOrganisation($orgId);

        /** @var LessonBookingRequest[] $requests */
        $requests = LessonBookingRequest::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'status' => LessonBookingRequest::STATUS_PENDING,
            ])
            ->with(['learner'])
            ->orderBy(['requested_starts_at' => SORT_ASC])
            ->limit(5)
            ->all();

        $actions = [];
        foreach ($requests as $request) {
            $learner = $request->learner;
            if ($learner === null) {
                continue;
            }
            $local = OrganisationTime::utcToLocal($request->requested_starts_at, $organisation);
            $ends = $local->modify('+' . (int) $request->duration_minutes . ' minutes');
            $name = $this->firstName($learner->fullName);
            $typeLabel = $request->type === LessonBookingRequest::TYPE_RESCHEDULE
                ? 'Reschedule request'
                : 'Lesson request';

            $actions[] = [
                'id' => 'booking_request:' . (int) $request->id,
                'kind' => 'booking_request',
                'priority' => 12,
                'title' => $name . ' · ' . $local->format('D j M') . ' · ' . $local->format('H:i'),
                'detail' => $typeLabel . ' · ' . $this->durationAdjective((int) $request->duration_minutes),
                'cta_label' => 'Review',
                'cta_path' => '/booking-requests/' . (int) $request->id,
                'learner_id' => (int) $learner->id,
                'lesson_id' => $request->original_lesson_id !== null
                    ? (int) $request->original_lesson_id
                    : null,
                'request_id' => (int) $request->id,
                'starts_at_time' => $local->format('H:i'),
                'ends_at_time' => $ends->format('H:i'),
                'pickup_address' => $request->pickup_address,
            ];
        }

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function emptySeatActions(Organisation $organisation, DateTimeImmutable $nowUtc): array
    {
        $horizon = $nowUtc->modify('+' . self::EMPTY_SEAT_HORIZON_DAYS . ' days')->format('Y-m-d H:i:s');
        $nowSql = $nowUtc->format('Y-m-d H:i:s');

        /** @var Lesson[] $cancelled */
        $cancelled = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_CANCELLED])
            ->andWhere(['>=', 'starts_at', $nowSql])
            ->andWhere(['<', 'starts_at', $horizon])
            ->with(['learner'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->limit(8)
            ->all();

        $actions = [];
        foreach ($cancelled as $lesson) {
            $recovery = $this->emptySeats->forCancelledLesson($lesson, $organisation, $nowUtc);
            if (empty($recovery['applicable'])) {
                continue;
            }
            if ((int) ($recovery['match_count'] ?? 0) < 1) {
                continue;
            }

            $minutes = (int) ($recovery['duration_minutes'] ?? $lesson->duration_minutes);
            $durationBit = $this->durationAdjective($minutes);
            $when = $this->relativeDayLabel(
                OrganisationTime::utcToLocal($lesson->starts_at, $organisation),
                $nowUtc,
                $organisation,
            );

            $actions[] = [
                'id' => 'empty_seat:' . (int) $lesson->id,
                'kind' => 'empty_seat',
                'priority' => 10,
                'title' => 'Fill ' . $when . '’s ' . $durationBit . ' cancellation',
                'detail' => (string) ($recovery['match_summary'] ?? ''),
                'cta_label' => 'View matches',
                'cta_path' => '/lessons/' . (int) $lesson->id,
                'learner_id' => null,
                'lesson_id' => (int) $lesson->id,
                'match_count' => (int) $recovery['match_count'],
                'best_match' => $recovery['best_match'] ?? null,
            ];
        }

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rebookActions(DateTimeImmutable $nowUtc): array
    {
        $items = $this->continuity->needsAttention($nowUtc);
        $actions = [];
        foreach ($items as $item) {
            $detailParts = [];
            if (!empty($item['usual_cadence'])) {
                $detailParts[] = 'Usually ' . $item['usual_cadence'];
            }
            $detailParts[] = 'no future lesson';

            $actions[] = [
                'id' => 'rebook:' . (int) $item['learner_id'],
                'kind' => 'rebook',
                'priority' => 20,
                'title' => 'Rebook ' . $this->firstName((string) $item['learner_name']),
                'detail' => implode(' · ', $detailParts),
                'cta_label' => 'Book',
                'cta_path' => '/lessons/new?learner_id=' . (int) $item['learner_id'],
                'learner_id' => (int) $item['learner_id'],
                'lesson_id' => null,
                'reasons' => $item['reasons'] ?? [],
            ];
        }

        return $actions;
    }

    /**
     * Approaching test with no lesson in the coming week — genuine scheduling gap.
     *
     * @param array<int, true> $skipLearnerIds already covered by rebook actions
     * @return list<array<string, mixed>>
     */
    private function testGapActions(
        Organisation $organisation,
        DateTimeImmutable $nowUtc,
        array $skipLearnerIds = [],
    ): array {
        $tz = OrganisationTime::timezoneFor($organisation);
        $nowLocal = $nowUtc->setTimezone($tz);
        $weekEndLocal = $nowLocal->setTime(0, 0, 0)->modify('+7 days');
        $weekEndUtc = $weekEndLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $nowSql = $nowUtc->format('Y-m-d H:i:s');

        /** @var Learner[] $learners */
        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->andWhere(['not', ['test_date' => null]])
            ->all();

        $actions = [];
        foreach ($learners as $learner) {
            $learnerId = (int) $learner->id;
            if (isset($skipLearnerIds[$learnerId])) {
                continue;
            }
            $journey = $this->tests->build($learner, $organisation, $nowUtc);
            if ($journey === null) {
                continue;
            }
            $days = (int) $journey['days_until'];
            if ($days < 1 || $days > self::TEST_GAP_HORIZON_DAYS) {
                continue;
            }

            $hasLessonNextWeek = TenantContext::scopeByOrganisation(Lesson::find())
                ->andWhere([
                    'learner_id' => (int) $learner->id,
                    'status' => Lesson::STATUS_SCHEDULED,
                ])
                ->andWhere(['>=', 'starts_at', $nowSql])
                ->andWhere(['<', 'starts_at', $weekEndUtc])
                ->exists();

            if ($hasLessonNextWeek) {
                continue;
            }

            // Already covered by rebook (no future at all) — still useful if they have
            // a future lesson beyond next week; only emit when they lack next-week cover.
            $actions[] = [
                'id' => 'test_gap:' . (int) $learner->id,
                'kind' => 'test_gap',
                'priority' => 30,
                'title' => $this->firstName($learner->fullName) . '’s test is in ' . $days . ' days',
                'detail' => 'No lesson booked next week',
                'cta_label' => 'Book',
                'cta_path' => '/lessons/new?learner_id=' . (int) $learner->id,
                'learner_id' => (int) $learner->id,
                'lesson_id' => null,
                'days_until_test' => $days,
            ];
        }

        return $actions;
    }

    /**
     * Outlook-style test prep reminders when days_until matches pupil offsets.
     *
     * @return list<array<string, mixed>>
     */
    private function testPrepActions(Organisation $organisation, DateTimeImmutable $nowUtc): array
    {
        /** @var Learner[] $learners */
        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->andWhere(['not', ['test_date' => null]])
            ->all();

        $actions = [];
        foreach ($learners as $learner) {
            $journey = $this->tests->build($learner, $organisation, $nowUtc);
            if ($journey === null) {
                continue;
            }
            $days = (int) $journey['days_until'];
            if ($days < 0) {
                continue;
            }
            $offsets = $learner->reminderOffsets();
            if (!in_array($days, $offsets, true)) {
                continue;
            }

            $bits = array_filter([
                $journey['test_centre'] ?? null,
                $journey['practical_test_time'] ?? null,
                $journey['cancel_by_label'] ?? null,
                $journey['hours_booked_label'] ?? null,
                isset($journey['latest_mock']['result_label'])
                    ? 'Mock: ' . $journey['latest_mock']['result_label']
                    : 'No mock recorded',
                $journey['syllabus_line'] ?? null,
            ]);

            $actions[] = [
                'id' => 'test_prep:' . (int) $learner->id . ':' . $days,
                'kind' => 'test_prep',
                'priority' => 18,
                'title' => $this->firstName($learner->fullName) . ' · ' . $journey['countdown_label'],
                'detail' => implode(' · ', $bits),
                'cta_label' => 'Open',
                'cta_path' => '/pupils/' . (int) $learner->id,
                'learner_id' => (int) $learner->id,
                'lesson_id' => null,
                'days_until_test' => $days,
            ];
        }

        return $actions;
    }

    /**
     * Unseen portal profile / place changes.
     *
     * @return list<array<string, mixed>>
     */
    private function profileChangeActions(): array
    {
        $events = (new LearnerProfileChangeService())->unseenForOrganisation(5);
        $actions = [];
        foreach ($events as $event) {
            $changeBits = [];
            foreach (($event['changes'] ?? []) as $change) {
                if (!is_array($change)) {
                    continue;
                }
                $from = trim((string) ($change['from'] ?? ''));
                $to = trim((string) ($change['to'] ?? ''));
                if ($from === '' && $to === '') {
                    continue;
                }
                $changeBits[] = ($from !== '' ? $from : '—') . ' → ' . ($to !== '' ? $to : '—');
            }
            $name = $this->firstName((string) ($event['learner_name'] ?? 'Pupil'));
            $actions[] = [
                'id' => 'profile_change:' . (int) $event['id'],
                'kind' => 'profile_change',
                'priority' => 22,
                'title' => $name . ' updated their details',
                'detail' => $changeBits !== []
                    ? implode('; ', array_slice($changeBits, 0, 2))
                    : (string) ($event['summary'] ?? ''),
                'cta_label' => 'Open',
                'cta_path' => '/pupils/' . (int) $event['learner_id'],
                'learner_id' => (int) $event['learner_id'],
                'lesson_id' => null,
                'event_id' => (int) $event['id'],
            ];
        }

        return $actions;
    }

    /**
     * Submitted intakes awaiting instructor review.
     *
     * @return list<array<string, mixed>>
     */
    private function intakeReviewActions(): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var LearnerIntake[] $intakes */
        $intakes = LearnerIntake::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'status' => LearnerIntake::STATUS_SUBMITTED,
            ])
            ->orderBy(['submitted_at' => SORT_ASC])
            ->limit(5)
            ->all();

        $actions = [];
        foreach ($intakes as $intake) {
            $answers = $intake->getAnswers();
            $identity = is_array($answers['identity'] ?? null) ? $answers['identity'] : [];
            $first = trim((string) ($identity['first_name'] ?? ''));
            $last = trim((string) ($identity['last_name'] ?? ''));
            $name = trim($first . ' ' . $last);
            if ($name === '') {
                $name = 'A new pupil';
            }

            $actions[] = [
                'id' => 'intake:' . (int) $intake->id,
                'kind' => 'intake_review',
                'priority' => 15,
                'title' => 'Review ' . $this->firstName($name) . '’s details',
                'detail' => 'They filled in their intake form',
                'cta_label' => 'Review',
                'cta_path' => '/pupils/intake/' . (int) $intake->id,
                'learner_id' => null,
                'lesson_id' => null,
                'intake_id' => (int) $intake->id,
            ];
        }

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function enquiryReviewActions(): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var Enquiry[] $enquiries */
        $enquiries = Enquiry::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'status' => Enquiry::STATUS_NEW,
            ])
            ->orderBy(['created_at' => SORT_ASC])
            ->limit(5)
            ->all();

        $actions = [];
        foreach ($enquiries as $enquiry) {
            $bits = array_filter([
                $enquiry->transmission ? ucfirst((string) $enquiry->transmission) : null,
                $enquiry->postcode,
            ]);
            $actions[] = [
                'id' => 'enquiry:' . (int) $enquiry->id,
                'kind' => 'enquiry_review',
                'priority' => 12,
                'title' => $enquiry->getFullName(),
                'detail' => $bits !== [] ? implode(' · ', $bits) : 'New enquiry',
                'cta_label' => 'Review',
                'cta_path' => '/pupils/enquiries/' . (int) $enquiry->id,
                'learner_id' => null,
                'lesson_id' => null,
                'enquiry_id' => (int) $enquiry->id,
            ];
        }

        return $actions;
    }

    private function durationAdjective(int $minutes): string
    {
        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours === 1 ? '1-hour' : $hours . '-hour';
        }

        return $minutes . '-minute';
    }

    private function relativeDayLabel(
        DateTimeImmutable $startLocal,
        DateTimeImmutable $nowUtc,
        Organisation $organisation,
    ): string {
        $nowDay = OrganisationTime::utcToLocal($nowUtc, $organisation)->setTime(0, 0, 0);
        $day = $startLocal->setTime(0, 0, 0);
        $diff = (int) $nowDay->diff($day)->format('%r%a');
        if ($diff === 0) {
            return 'today';
        }
        if ($diff === 1) {
            return 'tomorrow';
        }

        return strtolower($startLocal->format('l'));
    }

    private function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        return $parts[0] !== '' ? $parts[0] : $fullName;
    }
}
