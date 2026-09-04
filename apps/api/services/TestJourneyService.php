<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\models\Learner;
use app\models\Lesson;
use app\models\MockTest;
use app\models\Organisation;
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

        $lessonsBefore = $this->countScheduledBeforeTest($learner, $testLocal, $tz);

        $centre = trim((string) ($learner->test_centre ?? ''));
        $progress = trim((string) ($learner->last_lesson_summary ?? ''));
        $nextFocus = trim((string) ($learner->next_focus ?? ''));

        return [
            'test_date' => $raw,
            'test_date_display' => $testLocal->format('D j M Y'),
            'test_centre' => $centre !== '' ? $centre : null,
            'days_until' => $daysUntil,
            'weeks_until' => $this->weeksLabel($daysUntil),
            'countdown_label' => $this->countdownLabel($daysUntil),
            'lessons_booked_before_test' => $lessonsBefore,
            'progress_summary' => $progress !== '' ? $progress : null,
            'next_focus' => $nextFocus !== '' ? $nextFocus : null,
            'show_on_today' => $this->showOnToday($daysUntil),
            'recent_mocks' => $this->recentMocks($learner),
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
            'lessons_booked_before_test' => $full['lessons_booked_before_test'],
            'next_focus' => $full['next_focus'],
        ];
    }

    private function countScheduledBeforeTest(
        Learner $learner,
        DateTimeImmutable $testLocal,
        DateTimeZone $tz,
    ): int {
        // End of test day in org TZ → UTC exclusive upper bound for "before test day ends"
        // Count lessons that start before the test calendar day begins (teaching before test day).
        $testDayStartUtc = $testLocal->setTime(0, 0, 0)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        return (int) Lesson::find()
            ->andWhere([
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['<', 'starts_at', $testDayStartUtc])
            ->count();
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
