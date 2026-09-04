<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\TeachingAreaMatcher;
use app\models\Enquiry;
use app\models\LearnerAvailability;
use app\models\Lesson;
use app\models\Organisation;
use app\travel\TravelProviderFactory;
use app\travel\TravelTimeProvider;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Factual fit context for enquiries — no scores, no AI.
 */
class EnquiryFitService
{
    private const HORIZON_DAYS = 14;
    private const MAX_SLOTS = 3;

    private TravelTimeProvider $travel;
    private IntakeService $intake;

    public function __construct(?TravelTimeProvider $travel = null, ?IntakeService $intake = null)
    {
        $this->travel = $travel ?? TravelProviderFactory::make();
        $this->intake = $intake ?? new IntakeService();
    }

    /**
     * @return array<string, mixed>
     */
    public function context(Organisation $org, Enquiry $enquiry): array
    {
        $areas = $this->teachingAreas($org);
        $area = (new TeachingAreaMatcher())->check(
            (string) $enquiry->postcode,
            $areas,
            $org->service_area,
        );

        $flags = [];
        if ($area['status'] === 'within') {
            $flags[] = ['type' => 'area', 'label' => $area['label']];
        } elseif ($area['status'] === 'outside') {
            $flags[] = ['type' => 'area', 'label' => $area['label']];
        }

        $transmission = $this->transmissionFit($org, (string) ($enquiry->transmission ?? ''));
        if ($transmission !== null) {
            $flags[] = ['type' => 'transmission', 'label' => $transmission];
        }

        $start = $this->desiredStartLabel((string) ($enquiry->desired_start ?? ''));
        if ($start !== null) {
            $flags[] = ['type' => 'start', 'label' => $start];
        }

        if ($enquiry->practical_test_date) {
            $testDate = new DateTimeImmutable((string) $enquiry->practical_test_date, new DateTimeZone('UTC'));
            $days = (int) floor((new DateTimeImmutable('now', new DateTimeZone('UTC')))->diff($testDate)->days);
            $flags[] = ['type' => 'test', 'label' => 'Practical test in ' . max(0, $days) . ' days'];
        }

        $slots = $this->suggestedTimes($org, $enquiry);
        if ($slots['count'] > 0) {
            $flags[] = [
                'type' => 'availability',
                'label' => $slots['count'] . ' possible lesson time' . ($slots['count'] === 1 ? '' : 's') . ' in the next ' . self::HORIZON_DAYS . ' days',
            ];
        }

        return [
            'flags' => $flags,
            'area' => $area,
            'availability_summary' => $this->availabilitySummary($enquiry),
            'suggested_times' => $slots['items'],
            'suggested_times_count' => $slots['count'],
            'travel_note' => $this->travelNote($enquiry),
        ];
    }

    /**
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    public function suggestedTimes(Organisation $org, Enquiry $enquiry): array
    {
        $windows = $this->availabilityWindows($enquiry);
        if ($windows === []) {
            return ['count' => 0, 'items' => []];
        }

        $duration = $org->defaultLessonDurationMinutes();
        $pickup = trim((string) $enquiry->postcode);
        $tz = OrganisationTime::timezoneFor($org);
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $todayLocal = $nowUtc->setTimezone($tz)->setTime(0, 0, 0);
        $rangeEnd = $todayLocal->modify('+' . self::HORIZON_DAYS . ' days');

        $startUtc = $todayLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $rangeEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        /** @var Lesson[] $lessons */
        $lessons = Lesson::find()
            ->andWhere(['organisation_id' => (int) $org->id, 'status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $startUtc])
            ->andWhere(['<', 'starts_at', $endUtc])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $byDay = [];
        foreach ($lessons as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $key = $local->format('Y-m-d');
            $byDay[$key][] = $lesson;
        }

        $results = [];
        $increment = $org->bookingSlotIncrementMinutes();

        for ($cursor = $todayLocal; $cursor < $rangeEnd; $cursor = $cursor->modify('+1 day')) {
            if (!$org->isWorkingDay((int) $cursor->format('N'))) {
                continue;
            }
            $key = $cursor->format('Y-m-d');
            $dayLessons = $byDay[$key] ?? [];
            $blocks = $this->freeBlocksForDay($org, $cursor, $dayLessons);

            foreach ($blocks as $block) {
                $slotStart = $block['start_local'];
                $slotEnd = $block['end_local'];
                while ($slotStart->modify('+' . $duration . ' minutes') <= $slotEnd) {
                    $proposedEnd = $slotStart->modify('+' . $duration . ' minutes');
                    if (!$this->fitsAvailability($windows, (int) $slotStart->format('N'), $slotStart, $duration)) {
                        $slotStart = $slotStart->modify('+' . $increment . ' minutes');
                        continue;
                    }

                    $prevPickup = $block['prev_pickup'];
                    $travel = $this->travel->estimateDriveMinutes($prevPickup, $pickup);
                    if ($travel !== null && $block['prev_end_utc'] !== null) {
                        $earliest = $block['prev_end_utc']->modify('+' . $travel . ' minutes');
                        $proposedUtc = $slotStart->setTimezone(new DateTimeZone('UTC'));
                        if ($proposedUtc < $earliest) {
                            $slotStart = $slotStart->modify('+' . $increment . ' minutes');
                            continue;
                        }
                    }

                    $results[] = [
                        'date' => $key,
                        'date_label' => $slotStart->format('D j M'),
                        'time_label' => $slotStart->format('H:i') . '–' . $proposedEnd->format('H:i'),
                        'starts_at_local' => OrganisationTime::formatLocalIso($slotStart),
                        'duration_minutes' => $duration,
                        '_sort' => $key . ' ' . $slotStart->format('H:i'),
                    ];

                    if (count($results) >= self::MAX_SLOTS) {
                        break 2;
                    }
                    $slotStart = $slotStart->modify('+' . $increment . ' minutes');
                }
            }
        }

        usort($results, static fn ($a, $b) => strcmp((string) $a['_sort'], (string) $b['_sort']));
        $items = array_map(static function (array $row): array {
            unset($row['_sort']);

            return $row;
        }, array_slice($results, 0, self::MAX_SLOTS));

        return ['count' => count($results), 'items' => $items];
    }

    /**
     * @return list<array{start_local: DateTimeImmutable, end_local: DateTimeImmutable, prev_pickup: ?string, prev_end_utc: ?DateTimeImmutable}>
     */
    private function freeBlocksForDay(Organisation $org, DateTimeImmutable $dayLocal, array $dayLessons): array
    {
        [$wsH, $wsM] = array_map('intval', explode(':', $org->workStartTime()));
        [$weH, $weM] = array_map('intval', explode(':', $org->workEndTime()));
        $dayStart = $dayLocal->setTime($wsH, $wsM);
        $dayEnd = $dayLocal->setTime($weH, $weM);

        if ($dayLessons === []) {
            return [['start_local' => $dayStart, 'end_local' => $dayEnd, 'prev_pickup' => null, 'prev_end_utc' => null]];
        }

        usort($dayLessons, static fn (Lesson $a, Lesson $b) => strcmp((string) $a->starts_at, (string) $b->starts_at));
        $blocks = [];
        $cursor = $dayStart;
        $prevPickup = null;
        $prevEndUtc = null;

        foreach ($dayLessons as $lesson) {
            $lessonStart = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            if ($lessonStart > $cursor) {
                $blocks[] = [
                    'start_local' => $cursor,
                    'end_local' => $lessonStart,
                    'prev_pickup' => $prevPickup,
                    'prev_end_utc' => $prevEndUtc,
                ];
            }
            $prevPickup = trim((string) ($lesson->pickup_address ?? '')) ?: null;
            $prevEndUtc = new DateTimeImmutable((string) $lesson->ends_at, new DateTimeZone('UTC'));
            $cursor = OrganisationTime::utcToLocal($lesson->ends_at, $org);
        }

        if ($cursor < $dayEnd) {
            $blocks[] = [
                'start_local' => $cursor,
                'end_local' => $dayEnd,
                'prev_pickup' => $prevPickup,
                'prev_end_utc' => $prevEndUtc,
            ];
        }

        return $blocks;
    }

    /**
     * @param list<array{weekday: int, mode: string, start_time: ?string, end_time: ?string}> $windows
     */
    private function fitsAvailability(array $windows, int $weekday, DateTimeImmutable $startLocal, int $duration): bool
    {
        $dayWindows = array_values(array_filter($windows, static fn ($w) => (int) $w['weekday'] === $weekday));
        if ($dayWindows === []) {
            return false;
        }
        $startHm = $startLocal->format('H:i');
        $endHm = $startLocal->modify('+' . $duration . ' minutes')->format('H:i');

        foreach ($dayWindows as $window) {
            if ($this->windowCovers($window, $startHm, $endHm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{weekday: int, mode: string, start_time: ?string, end_time: ?string} $window
     */
    private function windowCovers(array $window, string $startHm, string $endHm): bool
    {
        $start = $this->hmToMinutes($startHm);
        $end = $this->hmToMinutes($endHm);

        return match ($window['mode']) {
            LearnerAvailability::MODE_FLEXIBLE => true,
            LearnerAvailability::MODE_AFTER => $window['start_time'] !== null
                && $start >= $this->hmToMinutes(substr((string) $window['start_time'], 0, 5)),
            LearnerAvailability::MODE_BEFORE => $window['end_time'] !== null
                && $end <= $this->hmToMinutes(substr((string) $window['end_time'], 0, 5)),
            LearnerAvailability::MODE_BETWEEN => $window['start_time'] !== null
                && $window['end_time'] !== null
                && $start >= $this->hmToMinutes(substr((string) $window['start_time'], 0, 5))
                && $end <= $this->hmToMinutes(substr((string) $window['end_time'], 0, 5)),
            default => false,
        };
    }

    private function hmToMinutes(string $hm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hm));

        return $h * 60 + $m;
    }

    /**
     * @return list<array{weekday: int, mode: string, start_time: ?string, end_time: ?string}>
     */
    private function availabilityWindows(Enquiry $enquiry): array
    {
        $availability = $enquiry->getAvailability();
        $answers = ['availability' => $availability];

        return $this->intake->availabilityWindowsFromAnswers($answers);
    }

    private function availabilitySummary(Enquiry $enquiry): string
    {
        $availability = $enquiry->getAvailability();
        $days = is_array($availability['days'] ?? null) ? $availability['days'] : [];
        $bits = [];
        foreach ($days as $day) {
            if (!is_array($day)) {
                continue;
            }
            $weekday = (int) ($day['weekday'] ?? 0);
            $slots = is_array($day['slots'] ?? null) ? $day['slots'] : [];
            if ($weekday < 1 || $slots === []) {
                continue;
            }
            $label = match ($weekday) {
                1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
                5 => 'Fri', 6 => 'Sat', default => 'Sun',
            };
            $bits[] = $label . ' · ' . implode(', ', array_map('strval', $slots));
        }

        return implode(' · ', $bits);
    }

    /**
     * @return list<string>
     */
    private function teachingAreas(Organisation $org): array
    {
        $raw = trim((string) ($org->profile_teaching_areas ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded)
            ? array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $decoded)))
            : [];
    }

    private function transmissionFit(Organisation $org, string $requested): ?string
    {
        $profile = trim((string) ($org->profile_transmission ?? ''));
        if ($requested === '' || $profile === '' || $profile === 'both') {
            return null;
        }
        if ($requested === 'either') {
            return null;
        }
        if ($requested === $profile) {
            return 'Matches ' . $profile . ' lessons';
        }

        return ucfirst($requested) . ' requested';
    }

    private function desiredStartLabel(string $code): ?string
    {
        return match ($code) {
            'asap' => 'Wants to start soon',
            'few_weeks' => 'Wants to start in the next few weeks',
            'next_month' => 'Wants to start next month',
            'flexible' => 'Flexible on start date',
            default => null,
        };
    }

    private function travelNote(Enquiry $enquiry): ?string
    {
        $pc = trim((string) $enquiry->postcode);
        if (preg_match('/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i', $pc) !== 1) {
            return 'Travel time will be checked once the pickup address is confirmed.';
        }

        return null;
    }
}
