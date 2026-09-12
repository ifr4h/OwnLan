<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\PortalContext;
use app\components\ServicePriceResolver;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\Learner;
use app\models\LearnerAvailability;
use app\models\LearnerPackage;
use app\models\Lesson;
use app\models\Organisation;
use app\models\OrganisationService;
use app\travel\TravelProviderFactory;
use app\travel\TravelTimeProvider;
use DateTimeImmutable;
use DateTimeZone;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Privacy-safe learner booking availability — suitable slots only, never the private diary.
 */
class LearnerBookingAvailabilityService
{
    private TravelTimeProvider $travel;

    public function __construct(
        ?TravelTimeProvider $travel = null,
    ) {
        $this->travel = $travel ?? TravelProviderFactory::make();
    }

    /**
     * Portal availability for the authenticated learner.
     *
     * @return array<string, mixed>
     */
    public function forPortalLearner(array $query = []): array
    {
        [$learner, $org, $instructor] = $this->requirePortalContext();
        $this->assertLearnerCanBrowse($org);

        $pickup = $this->resolvePickup($learner, $query['pickup_address'] ?? null);
        $duration = $this->resolveDuration($learner, $org, $query['duration_minutes'] ?? null);
        $excludeLessonId = isset($query['exclude_lesson_id']) ? (int) $query['exclude_lesson_id'] : null;

        return $this->buildAvailabilityPayload(
            $org,
            $instructor,
            $learner,
            $pickup,
            $duration,
            $excludeLessonId,
        );
    }

    /**
     * Instructor-side availability for a specific learner (e.g. suggest another time).
     *
     * @return array<string, mixed>
     */
    public function forInstructorLearner(int $learnerId, array $query = []): array
    {
        $org = $this->requireOrganisation();
        $learner = $this->findLearnerOwned($learnerId);
        $instructor = $this->requirePrimaryInstructor($org);

        $pickup = $this->resolvePickup($learner, $query['pickup_address'] ?? null);
        $duration = $this->resolveDuration($learner, $org, $query['duration_minutes'] ?? null);
        $excludeLessonId = isset($query['exclude_lesson_id']) ? (int) $query['exclude_lesson_id'] : null;

        return $this->buildAvailabilityPayload(
            $org,
            $instructor,
            $learner,
            $pickup,
            $duration,
            $excludeLessonId,
        );
    }

    /**
     * Revalidate a concrete slot before booking/acceptance. Throws on conflict.
     */
    public function assertSlotAvailable(
        Organisation $org,
        Instructor $instructor,
        Learner $learner,
        DateTimeImmutable $startUtc,
        int $durationMinutes,
        ?string $pickupAddress,
        ?int $excludeLessonId = null,
        ?DateTimeImmutable $nowUtc = null,
    ): void {
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $startUtc = $startUtc->setTimezone(new DateTimeZone('UTC'));
        $local = OrganisationTime::utcToLocal($startUtc, $org);
        $dayLessons = $this->scheduledLessonsForDay($org, (int) $instructor->id, $local, $excludeLessonId);
        $endUtc = $startUtc->modify('+' . $durationMinutes . ' minutes');
        foreach ($dayLessons as $lesson) {
            $lessonStart = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            $lessonEnd = $lessonStart->modify('+' . (int) $lesson->duration_minutes . ' minutes');
            if ($startUtc < $lessonEnd && $endUtc > $lessonStart) {
                throw new ConflictHttpException('That time has just been taken.');
            }
        }

        if (!$this->isSlotFeasible(
            $org,
            $instructor,
            $learner,
            $startUtc,
            $durationMinutes,
            $pickupAddress,
            $excludeLessonId,
            $nowUtc,
        )) {
            throw new BadRequestHttpException('That time is no longer available.');
        }
    }

    /**
     * Lock and verify no overlapping scheduled lesson exists.
     */
    public function lockAndAssertNoOverlap(
        Organisation $org,
        int $instructorId,
        DateTimeImmutable $startUtc,
        int $durationMinutes,
        ?int $excludeLessonId = null,
    ): void {
        $endUtc = $startUtc->modify('+' . $durationMinutes . ' minutes');
        $startSql = OrganisationTime::formatUtc($startUtc);
        $endSql = OrganisationTime::formatUtc($endUtc);

        $sql = <<<'SQL'
SELECT id FROM lessons
WHERE organisation_id = :org
  AND instructor_id = :instr
  AND status = :scheduled
  AND starts_at < :end
  AND (starts_at + (duration_minutes || ' minutes')::interval) > :start
SQL;
        $params = [
            ':org' => (int) $org->id,
            ':instr' => $instructorId,
            ':scheduled' => Lesson::STATUS_SCHEDULED,
            ':start' => $startSql,
            ':end' => $endSql,
        ];
        if ($excludeLessonId !== null) {
            $sql .= ' AND id <> :exclude';
            $params[':exclude'] = $excludeLessonId;
        }
        $sql .= ' FOR UPDATE';

        $rows = \Yii::$app->db->createCommand($sql, $params)->queryAll();
        if ($rows !== []) {
            throw new BadRequestHttpException('That time has just been taken.');
        }
    }

    public function isSlotFeasible(
        Organisation $org,
        Instructor $instructor,
        Learner $learner,
        DateTimeImmutable $startUtc,
        int $durationMinutes,
        ?string $pickupAddress,
        ?int $excludeLessonId,
        DateTimeImmutable $nowUtc,
    ): bool {
        $startUtc = $startUtc->setTimezone(new DateTimeZone('UTC'));
        $local = OrganisationTime::utcToLocal($startUtc, $org);

        if (!$this->withinBookingWindow($org, $startUtc, $nowUtc)) {
            return false;
        }
        if (!$org->isWithinWorkingHours($local, $durationMinutes)) {
            return false;
        }
        if (!$this->allowedDuration($org, $learner, $durationMinutes)) {
            return false;
        }

        $dayLessons = $this->scheduledLessonsForDay($org, (int) $instructor->id, $local, $excludeLessonId);
        $endUtc = $startUtc->modify('+' . $durationMinutes . ' minutes');

        foreach ($dayLessons as $lesson) {
            $lessonStart = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            $lessonEnd = $lessonStart->modify('+' . (int) $lesson->duration_minutes . ' minutes');
            if ($startUtc < $lessonEnd && $endUtc > $lessonStart) {
                return false;
            }
        }

        $pickup = $pickupAddress ?? $learner->default_pickup_address;

        // Travel-adjusted feasibility using org-scoped lessons only (portal-safe).
        $previous = null;
        $next = null;
        foreach ($dayLessons as $lesson) {
            $lessonStart = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            if ($lessonStart <= $startUtc) {
                $previous = $lesson;
            }
        }
        foreach ($dayLessons as $lesson) {
            $lessonStart = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            if ($lessonStart > $startUtc) {
                $next = $lesson;
                break;
            }
        }

        if ($previous !== null) {
            $prevEnd = (new DateTimeImmutable($previous->starts_at, new DateTimeZone('UTC')))
                ->modify('+' . (int) $previous->duration_minutes . ' minutes');
            $travelMin = $this->travel->estimateDriveMinutes(
                $previous->pickup_address,
                $pickup,
            );
            if ($travelMin !== null) {
                $earliest = $prevEnd->modify('+' . $travelMin . ' minutes');
                if ($startUtc < $earliest) {
                    return false;
                }
            }
        }

        if ($next !== null) {
            $nextStart = new DateTimeImmutable($next->starts_at, new DateTimeZone('UTC'));
            $travelMin = $this->travel->estimateDriveMinutes(
                $pickup,
                $next->pickup_address,
            );
            if ($travelMin !== null) {
                $latestEnd = $nextStart->modify('-' . $travelMin . ' minutes');
                if ($endUtc > $latestEnd) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAvailabilityPayload(
        Organisation $org,
        ?Instructor $instructor,
        Learner $learner,
        ?string $pickup,
        int $duration,
        ?int $excludeLessonId,
    ): array {
        if ($instructor === null) {
            throw new NotFoundHttpException('Instructor not found.');
        }

        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $tz = OrganisationTime::timezoneFor($org);
        $nowLocal = $nowUtc->setTimezone($tz);
        $horizonLocal = $nowLocal->setTime(0, 0, 0)
            ->modify('+' . $org->bookingAdvanceWeeks() . ' weeks')
            ->modify('+1 day');

        $slots = [];
        $cursor = $nowLocal->setTime(0, 0, 0);
        while ($cursor < $horizonLocal) {
            $daySlots = $this->slotsForDay(
                $org,
                $instructor,
                $learner,
                $cursor,
                $duration,
                $pickup,
                $excludeLessonId,
                $nowUtc,
            );
            if ($daySlots !== []) {
                $slots = array_merge($slots, $daySlots);
            }
            $cursor = $cursor->modify('+1 day');
        }

        $ranked = $this->rankSlots($slots, $learner, $org, $nowUtc);
        $suggested = $ranked[0] ?? null;
        $grouped = $this->groupSlotsByWeek($ranked, $org, $nowLocal);

        $creditMinutes = $this->remainingCreditMinutes((int) $learner->id, (int) $org->id);
        $pricePence = $this->estimatePricePence($org, $learner, $duration, $nowLocal);
        $creditAfter = max(0, $creditMinutes - $duration);

        return [
            'booking_mode' => $org->bookingMode(),
            'reschedule_mode' => $org->learnerRescheduleMode(),
            'can_cancel' => $org->learnerCanCancel(),
            'duration_minutes' => $duration,
            'duration_options' => $this->durationOptions($org, $learner),
            'pickup_address' => $pickup,
            'pickup_options' => $this->pickupOptions($learner, $pickup),
            'suggested' => $suggested,
            'days' => $grouped,
            'total_slots' => count($ranked),
            'price' => [
                'amount_pence' => $pricePence,
                'amount_label' => Money::formatPence($pricePence),
                'included_in_package' => $creditMinutes >= $duration,
                'package_balance_after_label' => $this->creditLabel($creditAfter),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function slotsForDay(
        Organisation $org,
        Instructor $instructor,
        Learner $learner,
        DateTimeImmutable $dayLocal,
        int $durationMinutes,
        ?string $pickup,
        ?int $excludeLessonId,
        DateTimeImmutable $nowUtc,
    ): array {
        $isoDow = (int) $dayLocal->format('N');
        if (!$org->isWorkingDay($isoDow)) {
            return [];
        }

        [$workStartMin, $workEndMin] = $this->workMinutes($org);
        $increment = $org->bookingSlotIncrementMinutes();
        $dayLessons = $this->scheduledLessonsForDay($org, (int) $instructor->id, $dayLocal, $excludeLessonId);

        $busy = [];
        foreach ($dayLessons as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $startMin = $this->localMinutes($local);
            $busy[] = [
                'start' => $startMin,
                'end' => $startMin + (int) $lesson->duration_minutes,
                'lesson' => $lesson,
            ];
        }
        usort($busy, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        $slots = [];
        for ($minute = $workStartMin; $minute + $durationMinutes <= $workEndMin; $minute += $increment) {
            $slotLocal = $dayLocal->setTime(intdiv($minute, 60), $minute % 60, 0);
            $startUtc = $slotLocal->setTimezone(new DateTimeZone('UTC'));

            if (!$this->withinBookingWindow($org, $startUtc, $nowUtc)) {
                continue;
            }

            // Quick overlap skip before travel checks.
            $endMin = $minute + $durationMinutes;
            $overlaps = false;
            foreach ($busy as $block) {
                if ($minute < $block['end'] && $endMin > $block['start']) {
                    $overlaps = true;
                    break;
                }
            }
            if ($overlaps) {
                continue;
            }

            if (!$this->isSlotFeasible(
                $org,
                $instructor,
                $learner,
                $startUtc,
                $durationMinutes,
                $pickup,
                $excludeLessonId,
                $nowUtc,
            )) {
                continue;
            }

            $slots[] = [
                'date' => $dayLocal->format('Y-m-d'),
                'date_label' => $dayLocal->format('D j M'),
                'week_label' => $this->weekLabel($dayLocal, $nowUtc->setTimezone(OrganisationTime::timezoneFor($org))),
                'starts_at_local' => OrganisationTime::formatLocalIso($slotLocal),
                'starts_at_time' => $slotLocal->format('H:i'),
                'ends_at_time' => $slotLocal->modify('+' . $durationMinutes . ' minutes')->format('H:i'),
                'duration_minutes' => $durationMinutes,
                '_rank_cadence' => 0,
                '_rank_availability' => 0,
                '_rank_usual_time' => 0,
            ];
        }

        return $slots;
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return list<array<string, mixed>>
     */
    private function rankSlots(
        array $slots,
        Learner $learner,
        Organisation $org,
        DateTimeImmutable $nowUtc,
    ): array {
        if ($slots === []) {
            return [];
        }

        $usualTime = null;
        $usualWeekday = null;
        $pattern = $this->schedulePattern($learner, $org);
        if ($pattern !== null) {
            $usualTime = $pattern['time'] ?? null;
            $weekdayName = strtolower((string) ($pattern['weekday'] ?? ''));
            $usualWeekday = match ($weekdayName) {
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                'sunday' => 7,
                default => null,
            };
        }

        /** @var LearnerAvailability[] $windows */
        $windows = LearnerAvailability::find()
            ->andWhere(['learner_id' => (int) $learner->id])
            ->all();

        foreach ($slots as &$slot) {
            $local = OrganisationTime::localToUtc($slot['starts_at_local'], $org);
            $local = OrganisationTime::utcToLocal($local, $org);
            $weekday = (int) $local->format('N');
            $hm = $local->format('H:i');

            if ($this->learnerAvailabilityFits($windows, $weekday, $hm, (int) $slot['duration_minutes'])) {
                $slot['_rank_availability'] = 2;
                $slot['recommendation'] = 'Fits your usual availability';
            }

            if ($usualTime !== null && $usualTime === $hm) {
                $slot['_rank_usual_time'] = 2;
                $slot['recommendation'] = $slot['recommendation'] ?? 'Similar time to your usual lessons';
            }

            if ($usualWeekday !== null && $usualWeekday === $weekday) {
                $slot['_rank_cadence'] = 1;
                if (!isset($slot['recommendation'])) {
                    $slot['recommendation'] = 'Fits your usual weekly schedule';
                }
            }
        }
        unset($slot);

        usort($slots, static function (array $a, array $b): int {
            $scoreA = $a['_rank_availability'] + $a['_rank_usual_time'] + $a['_rank_cadence'];
            $scoreB = $b['_rank_availability'] + $b['_rank_usual_time'] + $b['_rank_cadence'];
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            return strcmp((string) $a['starts_at_local'], (string) $b['starts_at_local']);
        });

        return array_map(static function (array $slot): array {
            unset($slot['_rank_cadence'], $slot['_rank_availability'], $slot['_rank_usual_time']);

            return $slot;
        }, $slots);
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return list<array<string, mixed>>
     */
    private function groupSlotsByWeek(
        array $slots,
        Organisation $org,
        DateTimeImmutable $nowLocal,
    ): array {
        $thisWeekStart = $nowLocal->modify('monday this week')->setTime(0, 0, 0);
        $nextWeekStart = $thisWeekStart->modify('+7 days');

        $groups = [];
        foreach ($slots as $slot) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $slot['date'], OrganisationTime::timezoneFor($org));
            if ($date === false) {
                continue;
            }
            if ($date < $thisWeekStart) {
                $weekKey = 'earlier';
                $weekLabel = 'Soon';
            } elseif ($date < $nextWeekStart) {
                $weekKey = 'this_week';
                $weekLabel = 'This week';
            } elseif ($date < $nextWeekStart->modify('+7 days')) {
                $weekKey = 'next_week';
                $weekLabel = 'Next week';
            } else {
                $weekKey = 'later';
                $weekLabel = 'Later';
            }

            if (!isset($groups[$weekKey])) {
                $groups[$weekKey] = [
                    'week_key' => $weekKey,
                    'week_label' => $weekLabel,
                    'days' => [],
                ];
            }

            $dateKey = (string) $slot['date'];
            if (!isset($groups[$weekKey]['days'][$dateKey])) {
                $groups[$weekKey]['days'][$dateKey] = [
                    'date' => $dateKey,
                    'date_label' => $slot['date_label'],
                    'times' => [],
                ];
            }
            $groups[$weekKey]['days'][$dateKey]['times'][] = [
                'starts_at_local' => $slot['starts_at_local'],
                'starts_at_time' => $slot['starts_at_time'],
                'ends_at_time' => $slot['ends_at_time'],
                'recommendation' => $slot['recommendation'] ?? null,
            ];
        }

        $ordered = [];
        foreach (['this_week', 'next_week', 'later', 'earlier'] as $key) {
            if (!isset($groups[$key])) {
                continue;
            }
            $groups[$key]['days'] = array_values($groups[$key]['days']);
            $ordered[] = $groups[$key];
        }

        return $ordered;
    }

    /**
     * @param list<LearnerAvailability> $windows
     */
    private function learnerAvailabilityFits(array $windows, int $weekday, string $startHm, int $duration): bool
    {
        $dayWindows = array_values(array_filter(
            $windows,
            static fn (LearnerAvailability $w) => (int) $w->weekday === $weekday,
        ));
        if ($dayWindows === []) {
            return false;
        }

        $endHm = DateTimeImmutable::createFromFormat('H:i', $startHm)
            ?->modify('+' . $duration . ' minutes')
            ->format('H:i') ?? $startHm;

        foreach ($dayWindows as $window) {
            if ($this->windowCovers($window, $startHm, $endHm)) {
                return true;
            }
        }

        return false;
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

    /**
     * @return list<Lesson>
     */
    private function scheduledLessonsForDay(
        Organisation $org,
        int $instructorId,
        DateTimeImmutable $dayLocal,
        ?int $excludeLessonId,
    ): array {
        $dayStart = $dayLocal->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');
        $startUtc = $dayStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $dayEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $query = Lesson::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'instructor_id' => $instructorId,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>=', 'starts_at', $startUtc])
            ->andWhere(['<', 'starts_at', $endUtc])
            ->orderBy(['starts_at' => SORT_ASC]);

        if ($excludeLessonId !== null) {
            $query->andWhere(['<>', 'id', $excludeLessonId]);
        }

        return $query->all();
    }

    private function withinBookingWindow(
        Organisation $org,
        DateTimeImmutable $startUtc,
        DateTimeImmutable $nowUtc,
    ): bool {
        $notice = $org->bookingMinimumNoticeHours();
        $earliest = $nowUtc->modify('+' . $notice . ' hours');
        if ($startUtc < $earliest) {
            return false;
        }

        $tz = OrganisationTime::timezoneFor($org);
        $latestLocal = $nowUtc->setTimezone($tz)
            ->setTime(0, 0, 0)
            ->modify('+' . $org->bookingAdvanceWeeks() . ' weeks')
            ->modify('+1 day')
            ->setTimezone(new DateTimeZone('UTC'));

        return $startUtc < $latestLocal;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function workMinutes(Organisation $org): array
    {
        [$wsH, $wsM] = array_map('intval', explode(':', $org->workStartTime()));
        [$weH, $weM] = array_map('intval', explode(':', $org->workEndTime()));

        return [$wsH * 60 + $wsM, $weH * 60 + $weM];
    }

    private function localMinutes(DateTimeImmutable $local): int
    {
        return ((int) $local->format('H')) * 60 + (int) $local->format('i');
    }

    private function weekLabel(DateTimeImmutable $day, DateTimeImmutable $nowLocal): string
    {
        $thisWeek = $nowLocal->modify('monday this week')->setTime(0, 0, 0);
        $nextWeek = $thisWeek->modify('+7 days');
        $d = $day->setTime(0, 0, 0);
        if ($d >= $thisWeek && $d < $nextWeek) {
            return 'This week';
        }
        if ($d >= $nextWeek && $d < $nextWeek->modify('+7 days')) {
            return 'Next week';
        }

        return 'Later';
    }

    private function resolvePickup(Learner $learner, mixed $raw): ?string
    {
        if ($raw !== null && trim((string) $raw) !== '') {
            return trim((string) $raw);
        }
        $default = trim((string) ($learner->default_pickup_address ?? ''));

        return $default === '' ? null : $default;
    }

    private function resolveDuration(Learner $learner, Organisation $org, mixed $raw): int
    {
        if ($raw !== null && $raw !== '') {
            $minutes = (int) $raw;
            if ($this->allowedDuration($org, $learner, $minutes)) {
                return $minutes;
            }
            throw new BadRequestHttpException('That lesson length is not available.');
        }

        return $this->defaultDurationForLearner($learner, $org);
    }

    private function defaultDurationForLearner(Learner $learner, Organisation $org): int
    {
        /** @var Lesson[] $history */
        $history = Lesson::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'learner_id' => (int) $learner->id,
            ])
            ->andWhere(['!=', 'status', Lesson::STATUS_CANCELLED])
            ->orderBy(['starts_at' => SORT_DESC])
            ->limit(8)
            ->all();
        if ($history === []) {
            return $org->defaultLessonDurationMinutes();
        }
        $values = array_map(static fn (Lesson $l) => (int) $l->duration_minutes, $history);
        $mode = $this->clearMode($values);

        return $mode !== null ? (int) $mode : $org->defaultLessonDurationMinutes();
    }

    /**
     * @param list<int|string> $values
     */
    private function clearMode(array $values): int|string|null
    {
        if ($values === []) {
            return null;
        }
        $counts = [];
        foreach ($values as $value) {
            $key = is_int($value) ? (string) $value : $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $modeKey = array_key_first($counts);
        $modeCount = $counts[$modeKey];
        $share = $modeCount / count($values);
        if ($modeCount < 3 || $share < 0.6) {
            return null;
        }

        return $modeKey;
    }

    /**
     * @return array{weekday: string, time: string}|null
     */
    private function schedulePattern(Learner $learner, Organisation $org): ?array
    {
        /** @var Lesson[] $history */
        $history = Lesson::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'learner_id' => (int) $learner->id,
            ])
            ->andWhere(['!=', 'status', Lesson::STATUS_CANCELLED])
            ->orderBy(['starts_at' => SORT_DESC])
            ->limit(8)
            ->all();
        if ($history === []) {
            return null;
        }
        $keys = [];
        foreach ($history as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $keys[] = $local->format('N') . '|' . $local->format('H:i');
        }
        $modeKey = $this->clearMode($keys);
        if (!is_string($modeKey)) {
            return null;
        }
        [$dow, $time] = explode('|', $modeKey, 2);
        $weekdayName = match ((int) $dow) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => 'Monday',
        };

        return ['weekday' => $weekdayName, 'time' => $time];
    }

    private function allowedDuration(Organisation $org, Learner $learner, int $minutes): bool
    {
        if ($minutes < 15 || $minutes > 480) {
            return false;
        }
        $allowed = $org->bookingAllowedDurations();
        if ($allowed === []) {
            return $minutes === $this->defaultDurationForLearner($learner, $org)
                || $minutes === $org->defaultLessonDurationMinutes();
        }

        return in_array($minutes, $allowed, true);
    }

    /**
     * @return list<int>
     */
    private function durationOptions(Organisation $org, Learner $learner): array
    {
        $allowed = $org->bookingAllowedDurations();
        if ($allowed === []) {
            $default = $this->defaultDurationForLearner($learner, $org);

            return array_values(array_unique([$default]));
        }

        return $allowed;
    }

    /**
     * @return list<array{label: string, address: string|null}>
     */
    private function pickupOptions(Learner $learner, ?string $current): array
    {
        $options = [];
        $default = trim((string) ($learner->default_pickup_address ?? ''));
        if ($default !== '') {
            $options[] = ['label' => 'Home', 'address' => $default];
        }
        if ($current !== null && $current !== '' && $current !== $default) {
            $options[] = ['label' => 'Selected', 'address' => $current];
        }

        return $options;
    }

    private function remainingCreditMinutes(int $learnerId, int $organisationId): int
    {
        $sum = LearnerPackage::find()
            ->andWhere([
                'organisation_id' => $organisationId,
                'learner_id' => $learnerId,
                'status' => LearnerPackage::STATUS_ACTIVE,
            ])
            ->sum('remaining_minutes');

        return (int) ($sum ?? 0);
    }

    private function estimatePricePence(
        Organisation $org,
        Learner $learner,
        int $durationMinutes,
        DateTimeImmutable $localNow,
    ): int {
        $service = OrganisationService::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'status' => OrganisationService::STATUS_ACTIVE,
                'is_default' => true,
            ])
            ->one();
        if ($service === null) {
            $service = OrganisationService::find()
                ->andWhere([
                    'organisation_id' => (int) $org->id,
                    'status' => OrganisationService::STATUS_ACTIVE,
                    'duration_minutes' => $durationMinutes,
                ])
                ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
                ->one();
        }

        return ServicePriceResolver::resolvePence(
            $org,
            null,
            $service,
            (int) $learner->id,
            $localNow,
            $durationMinutes,
        );
    }

    private function creditLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h';
        }
        if ($minutes % 60 === 0) {
            $h = (int) ($minutes / 60);

            return $h === 1 ? '1 hour' : $h . ' hours';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m . ' min';
        }

        return $h . 'h ' . $m . 'm';
    }

    private function assertLearnerCanBrowse(Organisation $org): void
    {
        if ($org->bookingMode() === Organisation::BOOKING_MODE_MANUAL) {
            throw new ForbiddenHttpException('Your instructor arranges lessons directly.');
        }
    }

    /**
     * @return array{0: Learner, 1: Organisation, 2: Instructor|null}
     */
    private function requirePortalContext(): array
    {
        $account = PortalContext::requireAccount();
        /** @var Learner|null $learner */
        $learner = Learner::findOne([
            'id' => (int) $account->learner_id,
            'organisation_id' => (int) $account->organisation_id,
        ]);
        if ($learner === null || $learner->archived_at !== null) {
            throw new NotFoundHttpException('Your learner record is unavailable.');
        }
        /** @var Organisation|null $org */
        $org = Organisation::findOne(['id' => (int) $account->organisation_id]);
        if ($org === null) {
            throw new NotFoundHttpException('Business not found.');
        }
        /** @var Instructor|null $instructor */
        $instructor = Instructor::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        return [$learner, $org, $instructor];
    }

    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $org;
    }

    private function findLearnerOwned(int $learnerId): Learner
    {
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }

    private function requirePrimaryInstructor(Organisation $org): Instructor
    {
        /** @var Instructor|null $instructor */
        $instructor = Instructor::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['id' => SORT_ASC])
            ->one();
        if ($instructor === null) {
            throw new NotFoundHttpException('Instructor not found.');
        }

        return $instructor;
    }
}
