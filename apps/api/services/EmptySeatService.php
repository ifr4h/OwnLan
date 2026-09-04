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
 * Cancellation Recovery / Empty Seat Engine V1.
 *
 * After a future lesson is cancelled, reuse gap matching to suggest replacements.
 * Instructor chooses — never auto-message, auto-offer, or auto-book.
 */
class EmptySeatService
{
    private GapMatchingService $matching;

    public function __construct(
        ?TravelTimeProvider $travel = null,
        ?GapMatchingService $matching = null,
    ) {
        $this->matching = $matching ?? new GapMatchingService(
            $travel ?? TravelProviderFactory::make(),
        );
    }

    /**
     * Build recovery payload for a lesson that has just been cancelled.
     *
     * @return array<string, mixed>
     */
    public function forCancelledLesson(
        Lesson $cancelled,
        Organisation $organisation,
        ?DateTimeImmutable $nowUtc = null,
    ): array {
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $slotStart = new DateTimeImmutable($cancelled->starts_at, new DateTimeZone('UTC'));
        $slotEnd = $slotStart->modify('+' . (int) $cancelled->duration_minutes . ' minutes');

        if ($slotEnd <= $nowUtc) {
            return $this->notApplicable('Slot is in the past.');
        }

        $cancelled->populateRelation('learner', $cancelled->learner);
        $name = $cancelled->learner?->fullName ?? 'Pupil';

        [$previous, $next] = $this->neighbours($organisation, $cancelled, $slotStart, $slotEnd);

        $prevEnd = $previous !== null
            ? (new DateTimeImmutable($previous->starts_at, new DateTimeZone('UTC')))
                ->modify('+' . (int) $previous->duration_minutes . ' minutes')
            : null;
        $prevPickup = $previous?->pickup_address;
        $nextPickup = $next?->pickup_address;

        $matches = $this->matching->matchOpenSlot(
            $organisation,
            $slotStart,
            $slotEnd,
            $prevEnd,
            $prevPickup,
            $nextPickup,
            [(int) $cancelled->learner_id],
            $nowUtc,
        );

        $startLocal = OrganisationTime::utcToLocal($slotStart, $organisation);
        $endLocal = OrganisationTime::utcToLocal($slotEnd, $organisation);

        return [
            'applicable' => true,
            'cancelled_lesson_id' => (int) $cancelled->id,
            'cancelled_learner_id' => (int) $cancelled->learner_id,
            'cancelled_learner_name' => $name,
            'headline' => $name . ' cancelled',
            'slot_label' => $this->slotLabel($startLocal, $endLocal, $nowUtc, $organisation),
            'starts_at_local' => OrganisationTime::formatLocalIso($startLocal),
            'ends_at_local' => OrganisationTime::formatLocalIso($endLocal),
            'starts_at_display' => $startLocal->format('H:i'),
            'ends_at_display' => $endLocal->format('H:i'),
            'duration_minutes' => (int) $cancelled->duration_minutes,
            'match_count' => count($matches),
            'match_summary' => $this->matchSummary(count($matches)),
            'matches' => $matches,
            'best_match' => $matches[0] ?? null,
        ];
    }

    /**
     * @return array{0: Lesson|null, 1: Lesson|null}
     */
    private function neighbours(
        Organisation $organisation,
        Lesson $cancelled,
        DateTimeImmutable $slotStart,
        DateTimeImmutable $slotEnd,
    ): array {
        $local = OrganisationTime::utcToLocal($slotStart, $organisation);
        $dayStart = $local->setTime(0, 0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $dayEnd = $local->setTime(0, 0, 0)->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        /** @var Lesson[] $dayLessons */
        $dayLessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $dayStart])
            ->andWhere(['<', 'starts_at', $dayEnd])
            ->andWhere(['!=', 'id', (int) $cancelled->id])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $previous = null;
        $next = null;
        foreach ($dayLessons as $lesson) {
            $start = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            $end = $start->modify('+' . (int) $lesson->duration_minutes . ' minutes');
            if ($end <= $slotStart) {
                $previous = $lesson;
            } elseif ($start >= $slotEnd && $next === null) {
                $next = $lesson;
            } elseif ($start > $slotStart && $next === null) {
                $next = $lesson;
            }
        }

        return [$previous, $next];
    }

    private function slotLabel(
        DateTimeImmutable $startLocal,
        DateTimeImmutable $endLocal,
        DateTimeImmutable $nowUtc,
        Organisation $organisation,
    ): string {
        $nowLocal = OrganisationTime::utcToLocal($nowUtc, $organisation)->setTime(0, 0, 0);
        $day = $startLocal->setTime(0, 0, 0);
        $diff = (int) $nowLocal->diff($day)->format('%r%a');
        if ($diff === 0) {
            $when = 'Today';
        } elseif ($diff === 1) {
            $when = 'Tomorrow';
        } else {
            $when = $startLocal->format('l j M');
        }

        return $when . ' ' . $startLocal->format('H:i') . '–' . $endLocal->format('H:i');
    }

    private function matchSummary(int $count): string
    {
        if ($count === 0) {
            return 'No suitable pupils found';
        }
        if ($count === 1) {
            return '1 suitable pupil found';
        }

        return $count . ' suitable pupils found';
    }

    /**
     * @return array<string, mixed>
     */
    private function notApplicable(string $reason): array
    {
        return [
            'applicable' => false,
            'reason' => $reason,
            'match_count' => 0,
            'matches' => [],
            'best_match' => null,
        ];
    }
}
