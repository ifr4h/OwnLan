<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Surfaces existing intelligence on pupil list, waiting list and records.
 *
 * Orchestrates ContinuityService, FinanceService and GapMatchingService — no duplicate logic.
 */
class PupilAttentionService
{
    public const TEST_HORIZON_DAYS = 14;
    public const GAP_HORIZON_DAYS = 14;

    private ContinuityService $continuity;
    private FinanceService $finance;
    private GapMatchingService $gaps;

    public function __construct(
        ?ContinuityService $continuity = null,
        ?FinanceService $finance = null,
        ?GapMatchingService $gaps = null,
    ) {
        $this->continuity = $continuity ?? new ContinuityService();
        $this->finance = $finance ?? new FinanceService();
        $this->gaps = $gaps ?? new GapMatchingService();
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{items: list<array<string, mixed>>, attention: array<string, mixed>}
     */
    public function enrichActiveList(array $items, ?DateTimeImmutable $nowUtc = null): array
    {
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $dueById = [];
        foreach ($this->continuity->needsAttention($nowUtc) as $due) {
            $dueById[(int) $due['learner_id']] = $due;
        }

        $learnerIds = array_map(static fn (array $item): int => (int) $item['id'], $items);
        $owedById = $this->finance->amountOwedByLearners($learnerIds);
        $outstandingPence = $this->finance->organisationOutstandingPence();
        $testsSoon = $this->countTestsSoon($nowUtc);

        $enriched = [];
        foreach ($items as $item) {
            $id = (int) $item['id'];
            $hint = $this->rowHint($dueById[$id] ?? null, $owedById[$id] ?? 0);
            if ($hint !== null) {
                $item['attention_hint'] = $hint;
            }
            $enriched[] = $item;
        }

        return [
            'items' => $enriched,
            'attention' => $this->listSummary(count($dueById), $outstandingPence, $testsSoon),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{items: list<array<string, mixed>>}
     */
    public function enrichWaitingList(array $items, ?DateTimeImmutable $nowUtc = null): array
    {
        if ($items === []) {
            return ['items' => $items];
        }

        $learnerIds = array_map(static fn (array $item): int => (int) $item['id'], $items);
        $matchesById = $this->gaps->gapOpportunitiesForLearners(
            $learnerIds,
            self::GAP_HORIZON_DAYS,
            $nowUtc,
        );

        $enriched = [];
        foreach ($items as $item) {
            $id = (int) $item['id'];
            $gapMatches = $matchesById[$id] ?? ['match_count' => 0, 'summary' => null, 'matches' => []];
            if ($gapMatches['match_count'] > 0) {
                $item['gap_matches'] = $gapMatches;
            }
            $enriched[] = $item;
        }

        return ['items' => $enriched];
    }

    private function countTestsSoon(DateTimeImmutable $nowUtc): int
    {
        $org = $this->requireOrganisation();
        $tz = OrganisationTime::timezoneFor($org);
        $todayLocal = $nowUtc->setTimezone($tz)->setTime(0, 0, 0);
        $horizonEnd = $todayLocal->modify('+' . self::TEST_HORIZON_DAYS . ' days')->format('Y-m-d');
        $today = $todayLocal->format('Y-m-d');

        return (int) TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->andWhere(['lifecycle' => Learner::LIFECYCLE_ACTIVE])
            ->andWhere(['not', ['test_date' => null]])
            ->andWhere(['between', 'test_date', $today, $horizonEnd])
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function listSummary(int $noFutureCount, int $outstandingPence, int $testsSoon): array
    {
        $lines = [];
        if ($noFutureCount > 0) {
            $lines[] = $noFutureCount === 1
                ? '1 pupil hasn\'t booked another lesson'
                : $noFutureCount . ' pupils haven\'t booked another lesson';
        }
        if ($outstandingPence > 0) {
            $lines[] = Money::formatPence($outstandingPence) . ' still to collect';
        }
        if ($testsSoon > 0) {
            $lines[] = $testsSoon === 1
                ? '1 test in the next ' . self::TEST_HORIZON_DAYS . ' days'
                : $testsSoon . ' tests in the next ' . self::TEST_HORIZON_DAYS . ' days';
        }

        return [
            'lines' => $lines,
            'no_future_booking_count' => $noFutureCount,
            'outstanding_pence' => $outstandingPence,
            'outstanding_label' => $outstandingPence > 0 ? Money::formatPence($outstandingPence) : null,
            'tests_soon_count' => $testsSoon,
        ];
    }

    /**
     * @param array<string, mixed>|null $dueItem
     */
    private function rowHint(?array $dueItem, int $owedPence): ?string
    {
        if ($dueItem !== null) {
            $usual = $dueItem['usual_cadence'] ?? null;
            if (is_string($usual) && $usual !== '') {
                return 'Usually ' . $usual . ' · no lesson booked';
            }

            return 'No lesson booked';
        }
        if ($owedPence > 0) {
            return Money::formatPence($owedPence) . ' owed';
        }

        return null;
    }

    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new \yii\web\NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }
}
