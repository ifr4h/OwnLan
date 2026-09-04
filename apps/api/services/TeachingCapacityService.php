<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\TeachingValueResolver;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\Organisation;
use app\services\GapMatchingService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Genuine usable diary teaching capacity for a future period.
 *
 * Uses working hours, scheduled lessons, and minimum lesson duration.
 * Does not count fragmented gaps shorter than MIN_GAP_MINUTES.
 */
class TeachingCapacityService
{
    public const MIN_USABLE_GAP_MINUTES = GapMatchingService::MIN_GAP_MINUTES;

    /**
     * @return array{
     *   usable_minutes: int,
     *   usable_hours_label: string,
     *   gap_count: int,
     *   gaps: list<array<string, mixed>>,
     *   pupil_gap_matches: int,
     *   distinct_pupils_matching: int,
     * }
     */
    public function capacityForPeriod(
        Organisation $org,
        DateTimeImmutable $fromLocal,
        DateTimeImmutable $toLocalInclusive,
        ?DateTimeImmutable $nowUtc = null,
    ): array {
        $tz = OrganisationTime::timezoneFor($org);
        $nowUtc = $nowUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowLocal = OrganisationTime::utcToLocal($nowUtc->format('Y-m-d H:i:s'), $org);

        $gapMatcher = new GapMatchingService();
        $usableMinutes = 0;
        $gapCount = 0;
        $gapsOut = [];
        $pupilGapMatches = 0;
        $distinctPupils = [];

        $cursor = $fromLocal->setTime(0, 0, 0);
        $endDay = $toLocalInclusive->setTime(0, 0, 0);

        while ($cursor <= $endDay) {
            if (!$org->isWorkingDay((int) $cursor->format('N'))) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $dateYmd = $cursor->format('Y-m-d');
            $dayLessons = $this->scheduledLessonsForDay($org, $cursor);
            $dayGaps = $this->usableGapsForDay($org, $cursor, $dayLessons, $nowLocal);

            foreach ($dayGaps as $gap) {
                $usableMinutes += $gap['minutes'];
                $gapCount++;
            }

            // Gap matching uses diary-serialized lessons (between-lesson gaps only).
            $serialized = array_map(
                fn (Lesson $lesson) => $this->serializeLessonForGaps($lesson, $org),
                $dayLessons,
            );
            $matchedGaps = $gapMatcher->gapsForDay($org, $dateYmd, $serialized, $nowUtc);
            foreach ($matchedGaps as $mg) {
                $matches = $mg['matches'] ?? [];
                $pupilGapMatches += count($matches);
                foreach ($matches as $match) {
                    $lid = (int) ($match['learner_id'] ?? 0);
                    if ($lid > 0) {
                        $distinctPupils[$lid] = true;
                    }
                }
            }

            // Include start/end-of-day gaps in output for accounts context.
            foreach ($dayGaps as $gap) {
                $gapsOut[] = [
                    'date' => $dateYmd,
                    'starts_at_display' => $gap['starts_at_display'],
                    'ends_at_display' => $gap['ends_at_display'],
                    'duration_minutes' => $gap['minutes'],
                    'potential_value_pence' => $this->potentialValueForGap($org, $gap['minutes']),
                    'potential_value_label' => \app\components\Money::formatPence(
                        $this->potentialValueForGap($org, $gap['minutes']),
                    ),
                ];
            }

            $cursor = $cursor->modify('+1 day');
        }

        return [
            'usable_minutes' => $usableMinutes,
            'usable_hours_label' => $this->hoursLabel($usableMinutes),
            'gap_count' => $gapCount,
            'gaps' => array_slice($gapsOut, 0, 8),
            'pupil_gap_matches' => $pupilGapMatches,
            'distinct_pupils_matching' => count($distinctPupils),
        ];
    }

    /**
     * @param list<Lesson> $dayLessons
     * @return list<array{minutes: int, starts_at_display: string, ends_at_display: string}>
     */
    private function usableGapsForDay(
        Organisation $org,
        DateTimeImmutable $dayLocal,
        array $dayLessons,
        DateTimeImmutable $nowLocal,
    ): array {
        [$workStart, $workEnd] = $this->workWindow($org, $dayLocal);
        if ($workEnd <= $workStart) {
            return [];
        }

        $busy = [];
        foreach ($dayLessons as $lesson) {
            $start = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $end = $start->modify('+' . (int) $lesson->duration_minutes . ' minutes');
            $busy[] = ['start' => $start, 'end' => $end];
        }
        usort($busy, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        $gaps = [];
        $cursor = $workStart;

        foreach ($busy as $block) {
            if ($block['start'] > $cursor) {
                $gaps[] = $this->gapSlice($cursor, $block['start'], $nowLocal, $dayLocal);
            }
            if ($block['end'] > $cursor) {
                $cursor = $block['end'];
            }
        }

        if ($cursor < $workEnd) {
            $gaps[] = $this->gapSlice($cursor, $workEnd, $nowLocal, $dayLocal);
        }

        return array_values(array_filter(
            $gaps,
            static fn (?array $g) => $g !== null && $g['minutes'] >= self::MIN_USABLE_GAP_MINUTES,
        ));
    }

    /**
     * @return array{minutes: int, starts_at_display: string, ends_at_display: string}|null
     */
    private function gapSlice(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        DateTimeImmutable $nowLocal,
        DateTimeImmutable $dayLocal,
    ): ?array {
        // Only future capacity within the day.
        if ($dayLocal->format('Y-m-d') === $nowLocal->format('Y-m-d') && $end <= $nowLocal) {
            return null;
        }
        if ($dayLocal->format('Y-m-d') === $nowLocal->format('Y-m-d') && $start < $nowLocal) {
            $start = $nowLocal;
        }
        if ($end <= $start) {
            return null;
        }
        $minutes = (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
        if ($minutes < self::MIN_USABLE_GAP_MINUTES) {
            return null;
        }

        return [
            'minutes' => $minutes,
            'starts_at_display' => $start->format('H:i'),
            'ends_at_display' => $end->format('H:i'),
        ];
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function workWindow(Organisation $org, DateTimeImmutable $dayLocal): array
    {
        [$wsH, $wsM] = array_map('intval', explode(':', $org->workStartTime()));
        [$weH, $weM] = array_map('intval', explode(':', $org->workEndTime()));
        $workStart = $dayLocal->setTime($wsH, $wsM, 0);
        $workEnd = $dayLocal->setTime($weH, $weM, 0);

        return [$workStart, $workEnd];
    }

    /**
     * @return list<Lesson>
     */
    private function scheduledLessonsForDay(Organisation $org, DateTimeImmutable $dayLocal): array
    {
        $startUtc = $dayLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $dayLocal->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $startUtc])
            ->andWhere(['<', 'starts_at', $endUtc])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        return $lessons;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLessonForGaps(Lesson $lesson, Organisation $org): array
    {
        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
        $endsLocal = $local->modify('+' . (int) $lesson->duration_minutes . ' minutes');
        $startsUtc = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
        $endsUtc = $startsUtc->modify('+' . (int) $lesson->duration_minutes . ' minutes');

        return [
            'id' => (int) $lesson->id,
            'status' => $lesson->status,
            'starts_at' => $startsUtc->format(DATE_ATOM),
            'ends_at' => $endsUtc->format(DATE_ATOM),
            'starts_at_local' => OrganisationTime::formatLocalIso($local),
            'ends_at_local' => OrganisationTime::formatLocalIso($endsLocal),
            'duration_minutes' => (int) $lesson->duration_minutes,
            'pickup_address' => $lesson->pickup_address,
        ];
    }

    private function potentialValueForGap(Organisation $org, int $minutes): int
    {
        $rate = TeachingValueResolver::hourlyRatePence($org);
        if ($rate <= 0) {
            return 0;
        }

        return \app\components\Money::lessonPriceFromHourlyRate($rate, $minutes);
    }

    private function hoursLabel(int $minutes): string
    {
        $hours = round($minutes / 60, 1);
        $formatted = rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');

        return $formatted . 'h';
    }
}
