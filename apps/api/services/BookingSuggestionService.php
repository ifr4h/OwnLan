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
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Deterministic booking prefills from a pupil's lesson history.
 *
 * No AI. Suggestions are optional defaults for the book form — never auto-book.
 */
class BookingSuggestionService
{
    /** How many recent non-cancelled lessons to inspect. */
    public const HISTORY_LIMIT = 8;

    /**
     * Mode must appear at least this many times to count as a clear pattern.
     */
    public const MIN_MODE_COUNT = 3;

    /**
     * Mode share of considered samples (0–1) required for a clear pattern.
     */
    public const MIN_MODE_SHARE = 0.6;

    /**
     * @return array<string, mixed>
     */
    public function suggestForLearner(int $learnerId, ?DateTimeImmutable $afterUtc = null): array
    {
        $org = $this->requireOrganisation();
        $learner = $this->findLearnerOwned($learnerId);
        $tz = OrganisationTime::timezoneFor($org);

        $afterUtc = $afterUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $history = $this->loadHistory($learnerId);

        $duration = $this->suggestDuration($history, $org);
        $pickup = $this->suggestPickup($history, $learner);
        $schedule = $this->suggestSchedule($history, $org, $tz, $afterUtc);

        return [
            'learner_id' => (int) $learner->id,
            'learner_name' => $learner->fullName,
            'history_count' => count($history),
            'duration_minutes' => $duration['value'],
            'duration_source' => $duration['source'],
            'pickup_address' => $pickup['value'],
            'pickup_source' => $pickup['source'],
            'suggested_starts_at_local' => $schedule['starts_at_local'],
            'suggested_starts_at_display' => $schedule['starts_at_display'],
            'schedule_source' => $schedule['source'],
            'schedule_pattern' => $schedule['pattern'],
        ];
    }

    /**
     * @return list<Lesson>
     */
    private function loadHistory(int $learnerId): array
    {
        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['learner_id' => $learnerId])
            ->andWhere(['!=', 'status', Lesson::STATUS_CANCELLED])
            ->orderBy(['starts_at' => SORT_DESC])
            ->limit(self::HISTORY_LIMIT)
            ->all();

        return $lessons;
    }

    /**
     * @param list<Lesson> $history
     * @return array{value: int, source: string}
     */
    private function suggestDuration(array $history, Organisation $org): array
    {
        if ($history === []) {
            return [
                'value' => $org->defaultLessonDurationMinutes(),
                'source' => 'default',
            ];
        }

        $values = array_map(static fn (Lesson $l) => (int) $l->duration_minutes, $history);
        $mode = $this->clearMode($values);
        if ($mode === null) {
            return [
                'value' => $org->defaultLessonDurationMinutes(),
                'source' => 'default',
            ];
        }

        return [
            'value' => (int) $mode,
            'source' => 'history',
        ];
    }

    /**
     * @param list<Lesson> $history
     * @return array{value: string|null, source: string}
     */
    private function suggestPickup(array $history, Learner $learner): array
    {
        $addresses = [];
        foreach ($history as $lesson) {
            $text = trim((string) ($lesson->pickup_address ?? ''));
            if ($text !== '') {
                $addresses[] = $text;
            }
        }

        $mode = $this->clearMode($addresses);
        if ($mode !== null) {
            return [
                'value' => (string) $mode,
                'source' => 'history',
            ];
        }

        $default = trim((string) ($learner->default_pickup_address ?? ''));
        if ($default !== '') {
            return [
                'value' => $default,
                'source' => 'learner_default',
            ];
        }

        return [
            'value' => null,
            'source' => 'none',
        ];
    }

    /**
     * @param list<Lesson> $history
     * @return array{
     *   starts_at_local: string|null,
     *   starts_at_display: string|null,
     *   source: string,
     *   pattern: array{weekday: string, time: string}|null
     * }
     */
    private function suggestSchedule(
        array $history,
        Organisation $org,
        DateTimeZone $tz,
        DateTimeImmutable $afterUtc,
    ): array {
        $empty = [
            'starts_at_local' => null,
            'starts_at_display' => null,
            'source' => 'none',
            'pattern' => null,
        ];

        if ($history === []) {
            return $empty;
        }

        $keys = [];
        foreach ($history as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            // N = ISO-8601 day-of-week (1=Mon … 7=Sun), H:i wall time
            $keys[] = $local->format('N') . '|' . $local->format('H:i');
        }

        $modeKey = $this->clearMode($keys);
        if ($modeKey === null || !is_string($modeKey)) {
            return $empty;
        }

        [$dow, $time] = explode('|', $modeKey, 2);
        $nextLocal = $this->nextOccurrence((int) $dow, $time, $afterUtc, $tz);
        $weekdayName = $nextLocal->format('l');

        return [
            'starts_at_local' => OrganisationTime::formatLocalIso($nextLocal),
            'starts_at_display' => OrganisationTime::formatLocalDisplay($nextLocal),
            'source' => 'pattern',
            'pattern' => [
                'weekday' => $weekdayName,
                'time' => $time,
            ],
        ];
    }

    /**
     * Next local wall time matching ISO weekday + HH:mm strictly after $afterUtc.
     */
    private function nextOccurrence(
        int $isoDow,
        string $hi,
        DateTimeImmutable $afterUtc,
        DateTimeZone $tz,
    ): DateTimeImmutable {
        $afterLocal = $afterUtc->setTimezone($tz);
        $cursor = $afterLocal->setTime(0, 0, 0);
        for ($i = 0; $i < 16; $i++) {
            $day = $cursor->modify('+' . $i . ' day');
            if ((int) $day->format('N') !== $isoDow) {
                continue;
            }
            $slot = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $day->format('Y-m-d') . ' ' . $hi . ':00',
                $tz,
            );
            if ($slot !== false && $slot > $afterLocal) {
                return $slot;
            }
        }

        // Should be unreachable within 16 days for a valid weekday.
        return $afterLocal->modify('+7 days');
    }

    /**
     * Return the clear modal value, or null when history is thin/inconsistent.
     *
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

        if ($modeCount < self::MIN_MODE_COUNT || $share < self::MIN_MODE_SHARE) {
            return null;
        }

        foreach ($values as $value) {
            if ((string) $value === (string) $modeKey) {
                return $value;
            }
        }

        return $modeKey;
    }

    /**
     * @throws UnauthorizedHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    private function requireOrganisation(): Organisation
    {
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
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();

        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }
}
