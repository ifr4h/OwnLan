<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\Organisation;
use app\travel\TravelProviderFactory;
use app\travel\TravelTimeProvider;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Smart Day V1 — travel feasibility between consecutive pickups.
 *
 * Warnings only; never blocks booking.
 */
class TravelFeasibilityService
{
    public const SEVERITY_OK = 'ok';
    public const SEVERITY_TIGHT = 'tight';
    public const SEVERITY_IMPOSSIBLE = 'impossible';

    /** Minutes of spare travel time before a gap feels comfortable. */
    public const COMFORT_BUFFER_MINUTES = 10;

    private TravelTimeProvider $provider;

    public function __construct(?TravelTimeProvider $provider = null)
    {
        $this->provider = $provider ?? TravelProviderFactory::make();
    }

    /**
     * Compare available gap vs estimated drive.
     *
     * @return array<string, mixed>|null Null when no estimate is possible.
     */
    public function assessGap(
        ?string $fromPickup,
        ?string $toPickup,
        int $availableMinutes,
        ?string $fromLearnerName = null,
        ?string $toLearnerName = null,
    ): ?array {
        $travelMinutes = $this->provider->estimateDriveMinutes($fromPickup, $toPickup);
        if ($travelMinutes === null) {
            return null;
        }

        $severity = $this->severity($availableMinutes, $travelMinutes);
        $shortfall = max(0, $travelMinutes - $availableMinutes);

        return [
            'severity' => $severity,
            'available_minutes' => $availableMinutes,
            'travel_minutes' => $travelMinutes,
            'shortfall_minutes' => $shortfall,
            'from_pickup' => $fromPickup,
            'to_pickup' => $toPickup,
            'from_learner_name' => $fromLearnerName,
            'to_learner_name' => $toLearnerName,
            'message' => $this->message($availableMinutes, $travelMinutes, $severity),
            'is_warning' => $severity !== self::SEVERITY_OK,
        ];
    }

    /**
     * Annotate ordered diary lessons with travel-to-next legs.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function annotateDiaryItems(array $items): array
    {
        $n = count($items);
        for ($i = 0; $i < $n; $i++) {
            $items[$i]['travel_to_next'] = null;
        }

        $scheduledIndexes = [];
        for ($i = 0; $i < $n; $i++) {
            if (($items[$i]['status'] ?? null) === Lesson::STATUS_SCHEDULED) {
                $scheduledIndexes[] = $i;
            }
        }

        for ($k = 0; $k < count($scheduledIndexes) - 1; $k++) {
            $i = $scheduledIndexes[$k];
            $j = $scheduledIndexes[$k + 1];
            $fromEnd = new DateTimeImmutable((string) $items[$i]['ends_at']);
            $toStart = new DateTimeImmutable((string) $items[$j]['starts_at']);
            $available = (int) floor(($toStart->getTimestamp() - $fromEnd->getTimestamp()) / 60);

            $assessment = $this->assessGap(
                isset($items[$i]['pickup_address']) ? (string) $items[$i]['pickup_address'] : null,
                isset($items[$j]['pickup_address']) ? (string) $items[$j]['pickup_address'] : null,
                $available,
                isset($items[$i]['learner_name']) ? (string) $items[$i]['learner_name'] : null,
                isset($items[$j]['learner_name']) ? (string) $items[$j]['learner_name'] : null,
            );
            if ($assessment === null) {
                continue;
            }

            $assessment['to_lesson_id'] = (int) $items[$j]['id'];
            $assessment['from_lesson_id'] = (int) $items[$i]['id'];
            $items[$i]['travel_to_next'] = $assessment;
        }

        return $items;
    }

    /**
     * Check a proposed lesson against neighbouring scheduled lessons that day.
     *
     * @return list<array<string, mixed>>
     */
    public function checkProposed(
        Organisation $organisation,
        string $startsAtUtc,
        int $durationMinutes,
        ?string $pickupAddress,
        ?string $learnerName = null,
        ?int $excludeLessonId = null,
    ): array {
        $startUtc = new DateTimeImmutable($startsAtUtc, new DateTimeZone('UTC'));
        $endUtc = $startUtc->modify('+' . $durationMinutes . ' minutes');
        $local = OrganisationTime::utcToLocal($startUtc, $organisation);
        $dayStartLocal = $local->setTime(0, 0, 0);
        $dayEndLocal = $dayStartLocal->modify('+1 day');
        $dayStartUtc = $dayStartLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $dayEndUtc = $dayEndLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        /** @var Lesson[] $neighbours */
        $neighbours = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $dayStartUtc])
            ->andWhere(['<', 'starts_at', $dayEndUtc])
            ->with(['learner'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        if ($excludeLessonId !== null) {
            $neighbours = array_values(array_filter(
                $neighbours,
                static fn (Lesson $l) => (int) $l->id !== $excludeLessonId,
            ));
        }

        $warnings = [];

        $previous = null;
        foreach ($neighbours as $lesson) {
            $lessonStart = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            if ($lessonStart <= $startUtc) {
                $previous = $lesson;
            }
        }

        if ($previous !== null) {
            $prevEnd = (new DateTimeImmutable($previous->starts_at, new DateTimeZone('UTC')))
                ->modify('+' . (int) $previous->duration_minutes . ' minutes');
            $available = (int) floor(($startUtc->getTimestamp() - $prevEnd->getTimestamp()) / 60);
            $assessment = $this->assessGap(
                $previous->pickup_address,
                $pickupAddress,
                $available,
                $previous->learner?->fullName,
                $learnerName,
            );
            if ($assessment !== null && $assessment['is_warning']) {
                $assessment['direction'] = 'from_previous';
                $assessment['other_lesson_id'] = (int) $previous->id;
                $warnings[] = $assessment;
            }
        }

        $next = null;
        foreach ($neighbours as $lesson) {
            $lessonStart = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            if ($lessonStart > $startUtc) {
                $next = $lesson;
                break;
            }
        }

        if ($next !== null) {
            $nextStart = new DateTimeImmutable($next->starts_at, new DateTimeZone('UTC'));
            $available = (int) floor(($nextStart->getTimestamp() - $endUtc->getTimestamp()) / 60);
            $assessment = $this->assessGap(
                $pickupAddress,
                $next->pickup_address,
                $available,
                $learnerName,
                $next->learner?->fullName,
            );
            if ($assessment !== null && $assessment['is_warning']) {
                $assessment['direction'] = 'to_next';
                $assessment['other_lesson_id'] = (int) $next->id;
                $warnings[] = $assessment;
            }
        }

        return $warnings;
    }

    public function severity(int $availableMinutes, int $travelMinutes): string
    {
        if ($availableMinutes < $travelMinutes) {
            return self::SEVERITY_IMPOSSIBLE;
        }
        if ($availableMinutes < $travelMinutes + self::COMFORT_BUFFER_MINUTES) {
            return self::SEVERITY_TIGHT;
        }

        return self::SEVERITY_OK;
    }

    public function message(int $availableMinutes, int $travelMinutes, string $severity): string
    {
        $gapLabel = $availableMinutes <= 0
            ? 'No gap between these lessons'
            : 'Only ' . $availableMinutes . ' minute' . ($availableMinutes === 1 ? '' : 's')
                . ' between these lessons';

        $drive = 'Estimated drive: ' . $travelMinutes . ' minute' . ($travelMinutes === 1 ? '' : 's') . '.';

        if ($severity === self::SEVERITY_IMPOSSIBLE) {
            return $gapLabel . '. ' . $drive;
        }
        if ($severity === self::SEVERITY_TIGHT) {
            return $gapLabel . ' — tight for travel. ' . $drive;
        }

        return $drive;
    }
}
