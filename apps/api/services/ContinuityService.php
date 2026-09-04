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
 * Continuity Engine V1 — deterministic “Needs Attention” for rebooking.
 *
 * No AI, no scores. Explainable reasons only.
 */
class ContinuityService
{
    /** Completed lessons inspected per pupil (newest first). */
    public const HISTORY_LIMIT = 8;

    /** Minimum completed lessons before we claim a usual cadence. */
    public const MIN_COMPLETED_FOR_PATTERN = 4;

    /** Soft gap (days) before a pupil without a clear pattern is surfaced. */
    public const SOFT_GAP_DAYS = 7;

    /**
     * Active pupils who may need rebooking, newest last-lesson first.
     *
     * @return list<array<string, mixed>>
     */
    public function needsAttention(?DateTimeImmutable $nowUtc = null): array
    {
        $org = $this->requireOrganisation();
        $tz = OrganisationTime::timezoneFor($org);
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowLocal = $nowUtc->setTimezone($tz);
        $nowUtcSql = $nowUtc->format('Y-m-d H:i:s');

        /** @var Learner[] $learners */
        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->andWhere(['lifecycle' => Learner::LIFECYCLE_ACTIVE])
            ->orderBy(['first_name' => SORT_ASC, 'last_name' => SORT_ASC])
            ->all();

        $items = [];
        foreach ($learners as $learner) {
            $item = $this->evaluateLearner($learner, $org, $tz, $nowLocal, $nowUtcSql);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        usort($items, static function (array $a, array $b): int {
            $da = $a['days_since_last_lesson'] ?? 0;
            $db = $b['days_since_last_lesson'] ?? 0;
            if ($da === $db) {
                return strcmp((string) $a['learner_name'], (string) $b['learner_name']);
            }

            return $db <=> $da;
        });

        return $items;
    }

    /**
     * Continuity headline for a pupil record — cadence plus booking horizon.
     *
     * @return array<string, mixed>|null
     */
    public function contextForLearner(int $learnerId, ?DateTimeImmutable $nowUtc = null): ?array
    {
        $org = $this->requireOrganisation();
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId, 'archived_at' => null])
            ->one();
        if (!$learner instanceof Learner) {
            return null;
        }

        $tz = OrganisationTime::timezoneFor($org);
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowLocal = $nowUtc->setTimezone($tz);
        $nowUtcSql = $nowUtc->format('Y-m-d H:i:s');

        /** @var Lesson[] $completed */
        $completed = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_COMPLETED,
            ])
            ->orderBy(['starts_at' => SORT_DESC])
            ->limit(self::HISTORY_LIMIT)
            ->all();

        if ($completed === []) {
            return null;
        }

        $pattern = $this->detectCadence($completed, $org);
        $headline = $pattern !== null ? $this->cadenceHeadline($pattern['label']) : null;

        /** @var Lesson|null $lastFuture */
        $lastFuture = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>=', 'starts_at', $nowUtcSql])
            ->orderBy(['starts_at' => SORT_DESC])
            ->one();

        if ($lastFuture !== null) {
            $anchorLocal = OrganisationTime::utcToLocal($lastFuture->starts_at, $org);
            $detail = 'Nothing booked after ' . $anchorLocal->format('l');
        } else {
            $lastLocal = OrganisationTime::utcToLocal($completed[0]->starts_at, $org);
            $detail = $headline !== null
                ? 'Nothing booked after ' . $lastLocal->format('l')
                : 'No lesson booked';
        }

        $attentionItem = $this->evaluateLearner($learner, $org, $tz, $nowLocal, $nowUtcSql);
        $lineParts = [];
        if ($headline !== null) {
            $lineParts[] = $headline;
        }
        $lineParts[] = lcfirst($detail);

        return [
            'headline' => $headline,
            'detail' => $detail,
            'line' => implode(' · ', $lineParts),
            'needs_attention' => $attentionItem !== null,
            'usual_cadence' => $pattern['label'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evaluateLearner(
        Learner $learner,
        Organisation $org,
        DateTimeZone $tz,
        DateTimeImmutable $nowLocal,
        string $nowUtcSql,
    ): ?array {
        $learnerId = (int) $learner->id;

        $hasFuture = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>=', 'starts_at', $nowUtcSql])
            ->exists();

        if ($hasFuture) {
            return null;
        }

        /** @var Lesson[] $completed */
        $completed = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_COMPLETED,
            ])
            ->orderBy(['starts_at' => SORT_DESC])
            ->limit(self::HISTORY_LIMIT)
            ->all();

        if ($completed === []) {
            // New pupil / no completed history — do not assume they need rebooking.
            return null;
        }

        $last = $completed[0];
        $lastLocal = OrganisationTime::utcToLocal($last->starts_at, $org);
        $daysSince = (int) $lastLocal->setTime(0, 0, 0)
            ->diff($nowLocal->setTime(0, 0, 0))
            ->format('%a');
        // diff direction: if last is in the future (clock skew), treat as 0
        if ($lastLocal > $nowLocal) {
            $daysSince = 0;
        }

        $pattern = $this->detectCadence($completed, $org);

        $reasons = ['No future lesson booked'];
        $reasons[] = $this->lastLessonReason($daysSince);

        if ($pattern !== null) {
            $grace = max(2, (int) floor($pattern['interval_days'] * 0.25));
            $overdueAfter = $pattern['interval_days'] + $grace;
            if ($daysSince < $overdueAfter) {
                // Pattern exists but they are not yet overdue — stay quiet.
                return null;
            }
            $reasons[] = 'Usually: ' . $pattern['label'];

            return $this->item($learner, $daysSince, $reasons, $pattern['label'], 'pattern_overdue');
        }

        // Insufficient / irregular history: soft surface only after a clear quiet gap.
        // Do not invent a “Usually” line.
        if ($daysSince < self::SOFT_GAP_DAYS) {
            return null;
        }

        return $this->item($learner, $daysSince, $reasons, null, 'no_future_booking');
    }

    /**
     * @param list<Lesson> $completedNewestFirst
     * @return array{interval_days: int, label: string}|null
     */
    private function detectCadence(array $completedNewestFirst, Organisation $org): ?array
    {
        if (count($completedNewestFirst) < self::MIN_COMPLETED_FOR_PATTERN) {
            return null;
        }

        $chronological = array_reverse($completedNewestFirst);
        $gaps = [];
        for ($i = 1, $n = count($chronological); $i < $n; $i++) {
            $prev = OrganisationTime::utcToLocal($chronological[$i - 1]->starts_at, $org)->setTime(0, 0, 0);
            $curr = OrganisationTime::utcToLocal($chronological[$i]->starts_at, $org)->setTime(0, 0, 0);
            $gaps[] = (int) $prev->diff($curr)->format('%a');
        }

        if (count($gaps) < 3) {
            return null;
        }

        // Use the most recent 3–5 gaps for “recent pattern”.
        $recentGaps = array_slice($gaps, -5);
        sort($recentGaps);
        $median = $recentGaps[(int) floor((count($recentGaps) - 1) / 2)];
        if ($median < 3 || $median > 28) {
            return null;
        }

        $tolerance = max(2, (int) round($median * 0.25));
        $within = 0;
        foreach ($recentGaps as $gap) {
            if (abs($gap - $median) <= $tolerance) {
                $within++;
            }
        }

        if ($within / count($recentGaps) < 0.6) {
            return null;
        }

        return [
            'interval_days' => $median,
            'label' => $this->cadenceLabel($median),
        ];
    }

    private function cadenceLabel(int $intervalDays): string
    {
        if ($intervalDays >= 5 && $intervalDays <= 9) {
            return 'weekly';
        }
        if ($intervalDays >= 12 && $intervalDays <= 16) {
            return 'fortnightly';
        }

        return 'every ' . $intervalDays . ' days';
    }

    private function cadenceHeadline(string $label): string
    {
        if ($label === 'weekly') {
            return 'Usually drives weekly';
        }
        if ($label === 'fortnightly') {
            return 'Usually drives fortnightly';
        }

        return 'Usually drives ' . $label;
    }

    private function lastLessonReason(int $daysSince): string
    {
        if ($daysSince <= 0) {
            return 'Last lesson: today';
        }
        if ($daysSince === 1) {
            return 'Last lesson: 1 day ago';
        }

        return 'Last lesson: ' . $daysSince . ' days ago';
    }

    /**
     * @param list<string> $reasons
     * @return array<string, mixed>
     */
    private function item(
        Learner $learner,
        int $daysSince,
        array $reasons,
        ?string $usualLabel,
        string $kind,
    ): array {
        return [
            'learner_id' => (int) $learner->id,
            'learner_name' => $learner->fullName,
            'kind' => $kind,
            'days_since_last_lesson' => $daysSince,
            'usual_cadence' => $usualLabel,
            'reasons' => $reasons,
        ];
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
}
