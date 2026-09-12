<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\models\Learner;
use app\models\Lesson;
use app\models\MockTest;
use app\models\Organisation;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Test Journey V1 — turn a practical test date into calm teaching context.
 *
 * Does not predict pass/fail, readiness, or scrape DVSA.
 * The instructor remains the decision-maker.
 */
class TestJourneyService
{
    /**
     * Show concise “Test in N days” on Today / lesson when within this horizon.
     */
    public const TODAY_HORIZON_DAYS = 56;

    /** Still mention a recent past test on teaching surfaces for this many days. */
    public const RECENT_PAST_DAYS = 7;

    /**
     * @return array<string, mixed>|null Null when no practical test date is set.
     */
    public function build(
        Learner $learner,
        Organisation $organisation,
        ?DateTimeImmutable $nowUtc = null,
    ): ?array {
        $raw = trim((string) ($learner->test_date ?? ''));
        if ($raw === '') {
            return null;
        }

        $tz = OrganisationTime::timezoneFor($organisation);
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $todayLocal = $nowUtc->setTimezone($tz)->setTime(0, 0, 0);

        $testLocal = DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz);
        if ($testLocal === false) {
            return null;
        }
        $testLocal = $testLocal->setTime(0, 0, 0);

        $daysUntil = (int) $todayLocal->diff($testLocal)->format('%r%a');
        // %r%a: signed day count (negative if test is in the past)

        $lessonStats = $this->scheduledBeforeTest($learner, $testLocal);

        $centre = trim((string) ($learner->test_centre ?? ''));
        $progress = trim((string) ($learner->last_lesson_summary ?? ''));
        $nextFocus = trim((string) ($learner->next_focus ?? ''));
        $time = trim((string) ($learner->practical_test_time ?? ''));
        $bookingRef = trim((string) ($learner->practical_test_booking_ref ?? ''));

        $cancel = $this->cancelByPayload($learner, $testLocal, $tz);
        $mocks = $this->recentMocks($learner);
        $latestMock = $mocks[0] ?? null;
        $syllabus = (new ProgressService())->syllabusCoverage(
            (int) $learner->id,
            (int) $learner->organisation_id,
        );

        return [
            'test_date' => $raw,
            'test_date_display' => $testLocal->format('D j M Y'),
            'test_centre' => $centre !== '' ? $centre : null,
            'practical_test_time' => $time !== '' ? $time : null,
            'booking_ref' => $bookingRef !== '' ? $bookingRef : null,
            'days_until' => $daysUntil,
            'weeks_until' => $this->weeksLabel($daysUntil),
            'countdown_label' => $this->countdownLabel($daysUntil),
            'lessons_booked_before_test' => $lessonStats['count'],
            'hours_booked_before_test' => $lessonStats['hours'],
            'hours_booked_label' => $lessonStats['hours_label'],
            'cancel_by_date' => $cancel['date'],
            'cancel_by_label' => $cancel['label'],
            'cancel_by_source' => $cancel['source'],
            'progress_summary' => $progress !== '' ? $progress : null,
            'next_focus' => $nextFocus !== '' ? $nextFocus : null,
            'show_on_today' => $this->showOnToday($daysUntil),
            'reminder_offsets' => $learner->reminderOffsets(),
            'latest_mock' => $latestMock,
            'syllabus_percent' => $syllabus['percent'] ?? 0,
            'syllabus_line' => $syllabus['line'] ?? null,
            'recent_mocks' => $mocks,
        ];
    }

    /**
     * Compact payload for Today / lesson cards.
     *
     * @return array<string, mixed>|null
     */
    public function buildCompact(
        Learner $learner,
        Organisation $organisation,
        ?DateTimeImmutable $nowUtc = null,
    ): ?array {
        $full = $this->build($learner, $organisation, $nowUtc);
        if ($full === null || !$full['show_on_today']) {
            return null;
        }

        return [
            'countdown_label' => $full['countdown_label'],
            'days_until' => $full['days_until'],
            'test_centre' => $full['test_centre'],
            'practical_test_time' => $full['practical_test_time'],
            'lessons_booked_before_test' => $full['lessons_booked_before_test'],
            'hours_booked_label' => $full['hours_booked_label'],
            'cancel_by_label' => $full['cancel_by_label'],
            'next_focus' => $full['next_focus'],
        ];
    }

    /**
     * @return array{count: int, hours: float, hours_label: string|null}
     */
    private function scheduledBeforeTest(Learner $learner, DateTimeImmutable $testLocal): array
    {
        $testDayStartUtc = $testLocal->setTime(0, 0, 0)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $query = Lesson::find()
            ->andWhere([
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['<', 'starts_at', $testDayStartUtc]);

        $count = (int) (clone $query)->count();
        $minutes = (int) ((clone $query)->sum('duration_minutes') ?? 0);
        $hours = round($minutes / 60, 1);
        $hoursLabel = null;
        if ($minutes > 0) {
            $hoursLabel = $hours === (float) (int) $hours
                ? ((int) $hours) . ' ' . ((int) $hours === 1 ? 'hour' : 'hours') . ' booked'
                : $hours . ' hours booked';
        }

        return [
            'count' => $count,
            'hours' => $hours,
            'hours_label' => $hoursLabel,
        ];
    }

    /**
     * @return array{date: string|null, label: string|null, source: string|null}
     */
    private function cancelByPayload(
        Learner $learner,
        DateTimeImmutable $testLocal,
        DateTimeZone $tz,
    ): array {
        $entered = trim((string) ($learner->practical_test_cancel_by ?? ''));
        if ($entered !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $entered, $tz);
            if ($dt !== false && $dt->format('Y-m-d') === $entered) {
                return [
                    'date' => $entered,
                    'label' => 'Cancel by ' . $dt->format('D j M'),
                    'source' => 'entered',
                ];
            }
        }

        $guided = $this->threeClearWorkingDaysBefore($testLocal);
        if ($guided === null) {
            return ['date' => null, 'label' => null, 'source' => null];
        }

        return [
            'date' => $guided->format('Y-m-d'),
            'label' => 'Cancel by ' . $guided->format('D j M') . ' (DVSA guidance)',
            'source' => 'guidance',
        ];
    }

    /**
     * Three clear working days before test (Mon–Fri only; bank holidays not modelled).
     */
    public function threeClearWorkingDaysBefore(DateTimeImmutable $testLocal): ?DateTimeImmutable
    {
        $cursor = $testLocal->setTime(0, 0, 0)->sub(new DateInterval('P1D'));
        $clear = 0;
        for ($i = 0; $i < 21; $i++) {
            if ((int) $cursor->format('N') <= 5) {
                $clear++;
                if ($clear >= 3) {
                    return $cursor;
                }
            }
            $cursor = $cursor->sub(new DateInterval('P1D'));
        }

        return null;
    }

    private function countdownLabel(int $daysUntil): string
    {
        if ($daysUntil === 0) {
            return 'Test today';
        }
        if ($daysUntil === 1) {
            return 'Test tomorrow';
        }
        if ($daysUntil === -1) {
            return 'Test was yesterday';
        }
        if ($daysUntil < 0) {
            return 'Test was ' . abs($daysUntil) . ' days ago';
        }
        if ($daysUntil < 14) {
            return 'Test in ' . $daysUntil . ' days';
        }

        $weeks = intdiv($daysUntil, 7);
        $rem = $daysUntil % 7;
        if ($rem === 0) {
            return $weeks === 1 ? 'Test in 1 week' : 'Test in ' . $weeks . ' weeks';
        }

        return 'Test in ' . $daysUntil . ' days';
    }

    private function weeksLabel(int $daysUntil): ?string
    {
        if ($daysUntil < 0) {
            return null;
        }
        if ($daysUntil < 7) {
            return $daysUntil === 1 ? '1 day' : $daysUntil . ' days';
        }
        $weeks = intdiv($daysUntil, 7);
        $rem = $daysUntil % 7;
        if ($rem === 0) {
            return $weeks === 1 ? '1 week' : $weeks . ' weeks';
        }

        return $daysUntil . ' days (~' . $weeks . ' wk)';
    }

    private function showOnToday(int $daysUntil): bool
    {
        if ($daysUntil < 0) {
            return abs($daysUntil) <= self::RECENT_PAST_DAYS;
        }

        return $daysUntil <= self::TODAY_HORIZON_DAYS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentMocks(Learner $learner): array
    {
        /** @var MockTest[] $mocks */
        $mocks = MockTest::find()
            ->andWhere([
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
                'status' => MockTest::STATUS_COMPLETED,
            ])
            ->orderBy(['started_at' => SORT_DESC])
            ->limit(5)
            ->all();

        $out = [];
        foreach ($mocks as $mock) {
            $ts = strtotime($mock->started_at . ' UTC');
            $out[] = [
                'id' => (int) $mock->id,
                'date_display' => $ts !== false ? gmdate('j M', $ts) : $mock->started_at,
                'result' => $mock->result,
                'result_label' => $mock->result === MockTest::RESULT_PASS ? 'Pass standard' : 'Not at pass standard',
                'driving_faults_count' => (int) $mock->driving_faults_count,
                'serious_faults_count' => (int) $mock->serious_faults_count,
                'dangerous_faults_count' => (int) $mock->dangerous_faults_count,
            ];
        }

        return $out;
    }
}
