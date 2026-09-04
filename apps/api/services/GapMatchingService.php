<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerAvailability;
use app\models\Lesson;
use app\models\Organisation;
use app\travel\TravelProviderFactory;
use app\travel\TravelTimeProvider;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Intelligent Gap Matching V1 — deterministic pupil suggestions for free diary gaps.
 *
 * No AI, no auto-book, explainable reasons only.
 */
class GapMatchingService
{
    /** Free wall-clock minutes before a gap is worth suggesting. */
    public const MIN_GAP_MINUTES = 60;

    /** Max suggestions per gap. */
    public const MAX_MATCHES = 3;

    private TravelTimeProvider $travel;
    private ContinuityService $continuity;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $dueCache = null;

    /** @var list<Learner>|null */
    private ?array $learnersCache = null;

    /** @var array<int, list<LearnerAvailability>>|null */
    private ?array $availabilityCache = null;

    /** @var array<int, true>|null */
    private ?array $futureCache = null;

    /** @var array<int, int>|null */
    private ?array $durationCache = null;

    /** @var array<int, string>|null */
    private ?array $durationSourceCache = null;

    private ?string $cacheNowKey = null;

    public function __construct(
        ?TravelTimeProvider $travel = null,
        ?ContinuityService $continuity = null,
    ) {
        $this->travel = $travel ?? TravelProviderFactory::make();
        $this->continuity = $continuity ?? new ContinuityService();
    }

    /**
     * Find meaningful gaps on a local calendar day and rank pupil matches.
     *
     * @param list<array<string, mixed>> $dayLessons diary-serialized lessons for one day
     * @return list<array<string, mixed>>
     */
    public function gapsForDay(
        Organisation $organisation,
        string $dateYmd,
        array $dayLessons,
        ?DateTimeImmutable $nowUtc = null,
    ): array {
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->warmCaches($nowUtc);

        $scheduled = array_values(array_filter(
            $dayLessons,
            static fn (array $l) => ($l['status'] ?? null) === Lesson::STATUS_SCHEDULED,
        ));
        usort($scheduled, static function (array $a, array $b): int {
            return strcmp((string) $a['starts_at'], (string) $b['starts_at']);
        });

        $rawGaps = [];
        for ($i = 0; $i < count($scheduled) - 1; $i++) {
            $prev = $scheduled[$i];
            $next = $scheduled[$i + 1];
            $prevEnd = new DateTimeImmutable((string) $prev['ends_at']);
            $nextStart = new DateTimeImmutable((string) $next['starts_at']);
            $minutes = (int) floor(($nextStart->getTimestamp() - $prevEnd->getTimestamp()) / 60);
            if ($minutes < self::MIN_GAP_MINUTES) {
                continue;
            }
            $rawGaps[] = [
                'prev' => $prev,
                'next' => $next,
                'gap_minutes' => $minutes,
                'starts_at' => $prevEnd,
                'ends_at' => $nextStart,
            ];
        }

        if ($rawGaps === []) {
            return [];
        }

        $gaps = [];
        foreach ($rawGaps as $gap) {
            $prevPickup = isset($gap['prev']['pickup_address'])
                ? (string) $gap['prev']['pickup_address']
                : null;
            $nextPickup = isset($gap['next']['pickup_address'])
                ? (string) $gap['next']['pickup_address']
                : null;
            $prevEnd = new DateTimeImmutable((string) $gap['prev']['ends_at']);

            $matches = $this->matchOpenSlot(
                $organisation,
                $gap['starts_at'],
                $gap['ends_at'],
                $prevEnd,
                $prevPickup,
                $nextPickup,
                [],
                $nowUtc,
            );

            $startLocal = OrganisationTime::utcToLocal($gap['starts_at'], $organisation);
            $endLocal = OrganisationTime::utcToLocal($gap['ends_at'], $organisation);
            $gaps[] = [
                'starts_at_local' => OrganisationTime::formatLocalIso($startLocal),
                'ends_at_local' => OrganisationTime::formatLocalIso($endLocal),
                'starts_at_display' => $startLocal->format('H:i'),
                'ends_at_display' => $endLocal->format('H:i'),
                'date' => $dateYmd,
                'weekday' => $startLocal->format('l'),
                'duration_minutes' => $gap['gap_minutes'],
                'label' => $this->gapLabel($gap['gap_minutes']),
                'previous_lesson_id' => (int) $gap['prev']['id'],
                'next_lesson_id' => (int) $gap['next']['id'],
                'matches' => $matches,
            ];
        }

        return $gaps;
    }

    /**
     * Rank pupils for a concrete open slot (diary gap or cancelled lesson).
     *
     * Shared by Intelligent Gap Matching and Empty Seat recovery.
     *
     * @param list<int> $excludeLearnerIds
     * @return list<array<string, mixed>>
     */
    public function matchOpenSlot(
        Organisation $organisation,
        DateTimeImmutable $slotStartUtc,
        DateTimeImmutable $slotEndUtc,
        ?DateTimeImmutable $previousEndsAtUtc,
        ?string $previousPickup,
        ?string $nextPickup,
        array $excludeLearnerIds = [],
        ?DateTimeImmutable $nowUtc = null,
        int $limit = self::MAX_MATCHES,
    ): array {
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->warmCaches($nowUtc);

        $slotStartUtc = $slotStartUtc->setTimezone(new DateTimeZone('UTC'));
        $slotEndUtc = $slotEndUtc->setTimezone(new DateTimeZone('UTC'));
        $slotMinutes = (int) floor(($slotEndUtc->getTimestamp() - $slotStartUtc->getTimestamp()) / 60);
        if ($slotMinutes < 15) {
            return [];
        }

        $exclude = [];
        foreach ($excludeLearnerIds as $id) {
            $exclude[(int) $id] = true;
        }

        $dueById = $this->dueCache ?? [];
        $learners = $this->learnersCache ?? [];
        $availabilityByLearner = $this->availabilityCache ?? [];
        $futureLearnerIds = $this->futureCache ?? [];
        $durations = $this->durationCache ?? [];
        $durationSources = $this->durationSourceCache ?? [];

        $weekday = (int) OrganisationTime::utcToLocal($slotStartUtc, $organisation)->format('N');
        $candidates = [];

        foreach ($learners as $learner) {
            $learnerId = (int) $learner->id;
            if (isset($exclude[$learnerId]) || isset($futureLearnerIds[$learnerId])) {
                continue;
            }

            $duration = $durations[$learnerId] ?? $organisation->defaultLessonDurationMinutes();
            if ($duration > $slotMinutes) {
                continue;
            }

            $pickup = trim((string) ($learner->default_pickup_address ?? ''));
            $pickup = $pickup === '' ? null : $pickup;

            $travelFromPrev = $this->travel->estimateDriveMinutes($previousPickup, $pickup);
            $travelToNext = $this->travel->estimateDriveMinutes($pickup, $nextPickup);

            if ($travelFromPrev !== null && $travelFromPrev > $slotMinutes) {
                continue;
            }
            if ($travelToNext !== null && $travelToNext > $slotMinutes) {
                continue;
            }

            $travelIn = $travelFromPrev ?? 0;
            $travelOut = $travelToNext ?? 0;

            $earliestStart = $slotStartUtc;
            if ($previousEndsAtUtc !== null && $travelFromPrev !== null) {
                $afterTravel = $previousEndsAtUtc
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->modify('+' . $travelFromPrev . ' minutes');
                if ($afterTravel > $earliestStart) {
                    $earliestStart = $afterTravel;
                }
            } elseif ($travelFromPrev !== null) {
                $earliestStart = $slotStartUtc->modify('+' . $travelIn . ' minutes');
            }

            $proposedEndUtc = $earliestStart->modify('+' . $duration . ' minutes');
            $latestEnd = $slotEndUtc->modify('-' . $travelOut . ' minutes');
            if ($proposedEndUtc > $latestEnd || $earliestStart >= $slotEndUtc) {
                continue;
            }
            if ($travelIn + $duration + $travelOut > $slotMinutes
                && $earliestStart === $slotStartUtc
                && $travelFromPrev === null) {
                // No previous anchor — still require duration+travelOut to fit.
                if ($duration + $travelOut > $slotMinutes) {
                    continue;
                }
            }

            $proposedLocal = OrganisationTime::utcToLocal($earliestStart, $organisation);
            $windows = $availabilityByLearner[$learnerId] ?? [];
            $availability = $this->availabilityFit($windows, $weekday, $proposedLocal, $duration);
            if ($availability['status'] === 'mismatch') {
                continue;
            }

            $due = $dueById[$learnerId] ?? null;
            $reasons = [];
            if ($availability['status'] === 'fits') {
                $reasons[] = 'Available';
            }
            if ($travelFromPrev !== null) {
                $reasons[] = $travelFromPrev . ' min from previous lesson';
            }
            if ($due !== null) {
                $reasons[] = 'Due another lesson';
            }
            if (($durationSources[$learnerId] ?? 'default') === 'history') {
                $reasons[] = 'Normally books ' . $this->durationPhrase($duration);
            }

            $candidates[] = [
                'learner_id' => $learnerId,
                'learner_name' => $learner->fullName,
                'suggested_starts_at_local' => OrganisationTime::formatLocalIso($proposedLocal),
                'suggested_duration_minutes' => $duration,
                'pickup_address' => $pickup,
                'reasons' => $reasons,
                'is_due' => $due !== null,
                'availability_known' => $availability['status'] === 'fits',
                'travel_from_previous_minutes' => $travelFromPrev,
                '_sort_due' => $due !== null ? 1 : 0,
                '_sort_avail' => $availability['status'] === 'fits' ? 1 : 0,
                '_sort_travel' => $travelFromPrev ?? 10_000,
                '_sort_name' => $learner->fullName,
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['_sort_due'] !== $b['_sort_due']) {
                return $b['_sort_due'] <=> $a['_sort_due'];
            }
            if ($a['_sort_avail'] !== $b['_sort_avail']) {
                return $b['_sort_avail'] <=> $a['_sort_avail'];
            }
            if ($a['_sort_travel'] !== $b['_sort_travel']) {
                return $a['_sort_travel'] <=> $b['_sort_travel'];
            }

            return strcmp((string) $a['_sort_name'], (string) $b['_sort_name']);
        });

        $top = array_slice($candidates, 0, max(1, $limit));

        return array_map(static function (array $row): array {
            unset($row['_sort_due'], $row['_sort_avail'], $row['_sort_travel'], $row['_sort_name']);

            return $row;
        }, $top);
    }

    private function durationPhrase(int $minutes): string
    {
        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours === 1 ? '1 hour' : $hours . ' hours';
        }

        return $minutes . ' minutes';
    }

    /**
     * @param list<LearnerAvailability> $windows
     * @return array{status: 'unknown'|'fits'|'mismatch', label: string|null}
     */
    private function availabilityFit(
        array $windows,
        int $weekday,
        DateTimeImmutable $proposedLocalStart,
        int $durationMinutes,
    ): array {
        $dayWindows = array_values(array_filter(
            $windows,
            static fn (LearnerAvailability $w) => (int) $w->weekday === $weekday,
        ));
        if ($dayWindows === []) {
            return ['status' => 'unknown', 'label' => null];
        }

        $startHm = $proposedLocalStart->format('H:i');
        $endLocal = $proposedLocalStart->modify('+' . $durationMinutes . ' minutes');
        $endHm = $endLocal->format('H:i');

        foreach ($dayWindows as $window) {
            if ($this->windowCovers($window, $startHm, $endHm)) {
                return [
                    'status' => 'fits',
                    'label' => 'Available',
                ];
            }
        }

        return ['status' => 'mismatch', 'label' => null];
    }

    private function windowCovers(LearnerAvailability $window, string $startHm, string $endHm): bool
    {
        $start = $this->hmToMinutes($startHm);
        $end = $this->hmToMinutes($endHm);

        return match ($window->mode) {
            LearnerAvailability::MODE_FLEXIBLE => true,
            LearnerAvailability::MODE_AFTER => $window->start_time !== null
                && $start >= $this->hmToMinutes(substr((string) $window->start_time, 0, 5)),
            LearnerAvailability::MODE_BEFORE => $window->end_time !== null
                && $end <= $this->hmToMinutes(substr((string) $window->end_time, 0, 5)),
            LearnerAvailability::MODE_BETWEEN => $window->start_time !== null
                && $window->end_time !== null
                && $start >= $this->hmToMinutes(substr((string) $window->start_time, 0, 5))
                && $end <= $this->hmToMinutes(substr((string) $window->end_time, 0, 5)),
            default => false,
        };
    }

    private function hmToMinutes(string $hm): int
    {
        $parts = explode(':', $hm);

        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }

    private function gapLabel(int $minutes): string
    {
        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours === 1 ? '1 hour gap' : $hours . ' hour gap';
        }
        if ($minutes > 60) {
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return $h . 'h ' . $m . 'm gap';
        }

        return $minutes . ' minute gap';
    }

    private function warmCaches(DateTimeImmutable $nowUtc): void
    {
        $key = $nowUtc->format('Y-m-d H:i:s');
        if ($this->cacheNowKey === $key && $this->learnersCache !== null) {
            return;
        }
        $this->cacheNowKey = $key;
        $this->dueCache = $this->dueIndex($nowUtc);
        $this->learnersCache = $this->activeLearners();
        $this->availabilityCache = $this->availabilityIndex();
        $this->futureCache = $this->learnersWithFutureBookings($nowUtc);
        $orgId = TenantContext::organisationId();
        $org = $orgId ? Organisation::findOne(['id' => $orgId]) : null;
        $defaultDuration = $org?->defaultLessonDurationMinutes() ?? Lesson::DEFAULT_DURATION_MINUTES;
        $durationMeta = $this->preferredDurations(
            array_map(static fn (Learner $l) => (int) $l->id, $this->learnersCache),
            $defaultDuration,
        );
        $this->durationCache = $durationMeta['minutes'];
        $this->durationSourceCache = $durationMeta['sources'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dueIndex(DateTimeImmutable $nowUtc): array
    {
        $items = $this->continuity->needsAttention($nowUtc);
        $map = [];
        foreach ($items as $item) {
            $map[(int) $item['learner_id']] = $item;
        }

        return $map;
    }

    /**
     * @return list<Learner>
     */
    private function activeLearners(): array
    {
        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->andWhere(['lifecycle' => [
                Learner::LIFECYCLE_ACTIVE,
                Learner::LIFECYCLE_WAITING,
            ]])
            ->orderBy(['first_name' => SORT_ASC, 'last_name' => SORT_ASC])
            ->all();

        return $learners;
    }

    /**
     * @return array<int, list<LearnerAvailability>>
     */
    private function availabilityIndex(): array
    {
        /** @var LearnerAvailability[] $rows */
        $rows = TenantContext::scopeByOrganisation(LearnerAvailability::find())
            ->orderBy(['learner_id' => SORT_ASC, 'weekday' => SORT_ASC])
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->learner_id][] = $row;
        }

        return $map;
    }

    /**
     * @return array<int, true>
     */
    private function learnersWithFutureBookings(DateTimeImmutable $nowUtc): array
    {
        $nowSql = $nowUtc->format('Y-m-d H:i:s');
        /** @var list<array{learner_id: int|string}> $rows */
        $rows = TenantContext::scopeByOrganisation(Lesson::find())
            ->select(['learner_id'])
            ->distinct()
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $nowSql])
            ->asArray()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['learner_id']] = true;
        }

        return $map;
    }

    /**
     * Preferred lesson duration per learner (history mode, else org default).
     *
     * @param list<int> $learnerIds
     * @return array{minutes: array<int, int>, sources: array<int, string>}
     */
    private function preferredDurations(array $learnerIds, int $defaultMinutes): array
    {
        $minutes = [];
        $sources = [];
        foreach ($learnerIds as $id) {
            $minutes[$id] = $defaultMinutes;
            $sources[$id] = 'default';
        }
        if ($learnerIds === []) {
            return ['minutes' => $minutes, 'sources' => $sources];
        }

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['learner_id' => $learnerIds])
            ->andWhere(['status' => [Lesson::STATUS_COMPLETED, Lesson::STATUS_SCHEDULED]])
            ->orderBy(['starts_at' => SORT_DESC])
            ->all();

        $byLearner = [];
        foreach ($lessons as $lesson) {
            $lid = (int) $lesson->learner_id;
            if (!isset($byLearner[$lid])) {
                $byLearner[$lid] = [];
            }
            if (count($byLearner[$lid]) < BookingSuggestionService::HISTORY_LIMIT) {
                $byLearner[$lid][] = (int) $lesson->duration_minutes;
            }
        }

        foreach ($byLearner as $lid => $durations) {
            $mode = $this->clearMode($durations);
            if ($mode !== null) {
                $minutes[$lid] = $mode;
                $sources[$lid] = 'history';
            }
        }

        return ['minutes' => $minutes, 'sources' => $sources];
    }

    /**
     * Diary gaps in the next N days where specific pupils appear as matches.
     *
     * @param list<int> $learnerIds
     * @return array<int, array{match_count: int, summary: string|null, matches: list<array<string, mixed>>}>
     */
    public function gapOpportunitiesForLearners(
        array $learnerIds,
        int $horizonDays = 14,
        ?DateTimeImmutable $nowUtc = null,
    ): array {
        $learnerIds = array_values(array_unique(array_map('intval', $learnerIds)));
        if ($learnerIds === []) {
            return [];
        }

        $orgId = TenantContext::organisationId();
        if ($orgId === null) {
            return [];
        }
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            return [];
        }

        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $tz = OrganisationTime::timezoneFor($organisation);
        $todayLocal = $nowUtc->setTimezone($tz)->setTime(0, 0, 0);
        $rangeEnd = $todayLocal->modify('+' . $horizonDays . ' days');

        $startUtc = $todayLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $rangeEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['>=', 'starts_at', $startUtc])
            ->andWhere(['<', 'starts_at', $endUtc])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $byDay = [];
        foreach ($lessons as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $organisation);
            $key = $local->format('Y-m-d');
            $byDay[$key][] = $this->minimalDiaryLesson($lesson, $organisation);
        }

        $targetIds = array_fill_keys($learnerIds, true);
        $rawByLearner = [];

        for ($cursor = $todayLocal; $cursor < $rangeEnd; $cursor = $cursor->modify('+1 day')) {
            $key = $cursor->format('Y-m-d');
            $gaps = $this->gapsForDay($organisation, $key, $byDay[$key] ?? [], $nowUtc);
            foreach ($gaps as $gap) {
                foreach ($gap['matches'] as $match) {
                    $lid = (int) $match['learner_id'];
                    if (!isset($targetIds[$lid])) {
                        continue;
                    }
                    $rawByLearner[$lid][] = [
                        'date' => $key,
                        'date_label' => $cursor->format('D j M'),
                        'time_label' => $gap['starts_at_display'] . '–' . $gap['ends_at_display'],
                        'gap_label' => (string) $gap['label'],
                        'reasons' => $match['reasons'],
                        'learner_id' => $lid,
                        'learner_name' => (string) $match['learner_name'],
                        'suggested_starts_at_local' => (string) $match['suggested_starts_at_local'],
                        'suggested_duration_minutes' => (int) $match['suggested_duration_minutes'],
                        'is_due' => (bool) ($match['is_due'] ?? false),
                        '_sort' => $key . ' ' . (string) $gap['starts_at_display'],
                    ];
                }
            }
        }

        $results = [];
        foreach ($learnerIds as $lid) {
            $opportunities = $rawByLearner[$lid] ?? [];
            usort($opportunities, static function (array $a, array $b): int {
                if ($a['is_due'] !== $b['is_due']) {
                    return $b['is_due'] <=> $a['is_due'];
                }

                return strcmp((string) $a['_sort'], (string) $b['_sort']);
            });
            $matches = array_slice(array_map(static function (array $row): array {
                unset($row['_sort'], $row['is_due']);

                return $row;
            }, $opportunities), 0, self::MAX_MATCHES);

            $count = count($opportunities);
            $summary = null;
            if ($count > 0 && isset($matches[0])) {
                $best = $matches[0];
                $summary = $count === 1
                    ? 'Could fit ' . $best['date_label'] . ' ' . $best['time_label']
                    : $count . ' diary gaps in the next ' . $horizonDays . ' days';
            }

            $results[$lid] = [
                'match_count' => $count,
                'summary' => $summary,
                'matches' => $matches,
            ];
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalDiaryLesson(Lesson $lesson, Organisation $organisation): array
    {
        $startsUtc = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
        $endsUtc = $startsUtc->modify('+' . (int) $lesson->duration_minutes . ' minutes');

        return [
            'id' => (int) $lesson->id,
            'status' => $lesson->status,
            'starts_at' => $lesson->starts_at,
            'ends_at' => $endsUtc->format('Y-m-d H:i:s'),
            'pickup_address' => $lesson->pickup_address,
            'starts_at_local' => OrganisationTime::formatLocalIso(
                OrganisationTime::utcToLocal($lesson->starts_at, $organisation),
            ),
        ];
    }

    /**
     * @param list<int> $values
     */
    private function clearMode(array $values): ?int
    {
        if (count($values) < 3) {
            return null;
        }
        $counts = array_count_values($values);
        arsort($counts);
        $top = (int) array_key_first($counts);
        $topCount = $counts[$top];
        if ($topCount / count($values) < 0.6) {
            return null;
        }

        return $top;
    }
}
