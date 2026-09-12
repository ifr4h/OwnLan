<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\Learner;
use app\models\LearnerSkillProgress;
use app\models\Lesson;
use app\models\Organisation;
use app\models\ProgressSkill;
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
        $testSoonIds = $this->testSoonLearnerIds($nowUtc);

        $enriched = [];
        foreach ($items as $item) {
            $id = (int) $item['id'];
            $needsBooking = isset($dueById[$id]);
            $owedPence = (int) ($owedById[$id] ?? 0);
            $hint = $this->rowHint($dueById[$id] ?? null, $owedPence);
            if ($hint !== null) {
                $item['attention_hint'] = $hint;
            }
            $item['needs_booking'] = $needsBooking;
            $item['outstanding_pence'] = $owedPence;
            $item['test_soon'] = isset($testSoonIds[$id]);
            $enriched[] = $item;
        }

        return [
            'items' => $this->enrichListColumns($enriched, $nowUtc),
            'attention' => $this->listSummary(count($dueById), $outstandingPence, $testsSoon),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{items: list<array<string, mixed>>}
     */
    public function enrichWaitingList(array $items, ?DateTimeImmutable $nowUtc = null): array
    {
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

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

        $withColumns = $this->enrichListColumns($enriched, $nowUtc);

        return ['items' => $withColumns];
    }

    /**
     * Start-from date labels + weekly availability summary for list rows.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function enrichAvailabilityContext(array $items, DateTimeImmutable $nowUtc): array
    {
        if ($items === []) {
            return $items;
        }

        $org = $this->requireOrganisation();
        $tz = OrganisationTime::timezoneFor($org);
        $todayLocal = $nowUtc->setTimezone($tz)->setTime(0, 0, 0);
        $learnerIds = array_map(static fn (array $item): int => (int) $item['id'], $items);
        $summaries = $this->availabilitySummariesByLearner($learnerIds);

        foreach ($items as &$item) {
            $id = (int) $item['id'];
            $raw = trim((string) ($item['available_from'] ?? ''));
            $item['availability_summary'] = $summaries[$id] ?? null;
            if ($raw === '') {
                $item['available_from_label'] = null;
                $item['available_from_month'] = null;
                $item['available_ready'] = false;
                continue;
            }
            $start = DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz);
            if ($start === false || $start->format('Y-m-d') !== $raw) {
                $item['available_from_label'] = null;
                $item['available_from_month'] = null;
                $item['available_ready'] = false;
                continue;
            }
            $start = $start->setTime(0, 0, 0);
            $days = (int) $todayLocal->diff($start)->format('%r%a');
            $ready = $days <= 0;
            $item['available_ready'] = $ready;
            $item['available_from_label'] = $ready
                ? 'Ready now'
                : 'From ' . $start->format('j M');
            $item['available_from_month'] = (int) $start->format('n');
        }
        unset($item);

        return $items;
    }

    /**
     * @param list<int> $learnerIds
     * @return array<int, string>
     */
    private function availabilitySummariesByLearner(array $learnerIds): array
    {
        if ($learnerIds === []) {
            return [];
        }

        /** @var \app\models\LearnerAvailability[] $rows */
        $rows = \app\models\LearnerAvailability::find()
            ->andWhere(['learner_id' => $learnerIds])
            ->orderBy(['weekday' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $byLearner = [];
        foreach ($rows as $row) {
            $id = (int) $row->learner_id;
            $day = match ((int) $row->weekday) {
                1 => 'Mon',
                2 => 'Tue',
                3 => 'Wed',
                4 => 'Thu',
                5 => 'Fri',
                6 => 'Sat',
                default => 'Sun',
            };
            $part = match ($row->mode) {
                \app\models\LearnerAvailability::MODE_FLEXIBLE => $day,
                \app\models\LearnerAvailability::MODE_AFTER => $day . ' after ' . substr((string) $row->start_time, 0, 5),
                \app\models\LearnerAvailability::MODE_BEFORE => $day . ' before ' . substr((string) $row->end_time, 0, 5),
                \app\models\LearnerAvailability::MODE_BETWEEN => $day . ' ' . substr((string) $row->start_time, 0, 5)
                    . '–' . substr((string) $row->end_time, 0, 5),
                default => $day,
            };
            $byLearner[$id][] = $part;
        }

        $out = [];
        foreach ($byLearner as $id => $parts) {
            $unique = array_values(array_unique($parts));
            if (count($unique) > 3) {
                $unique = array_slice($unique, 0, 3);
                $out[$id] = implode(' · ', $unique) . '…';
            } else {
                $out[$id] = implode(' · ', $unique);
            }
        }

        return $out;
    }

    /**
     * Optional list columns: instructor, lesson, progress, theory, practical.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function enrichListColumns(array $items, ?DateTimeImmutable $nowUtc = null): array
    {
        if ($items === []) {
            return $items;
        }

        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $org = $this->requireOrganisation();
        $tz = OrganisationTime::timezoneFor($org);
        $orgId = (int) $org->id;
        $learnerIds = array_map(static fn (array $item): int => (int) $item['id'], $items);

        $defaultInstructor = Instructor::find()
            ->andWhere(['organisation_id' => $orgId])
            ->orderBy(['id' => SORT_ASC])
            ->one();
        $defaultInstructorName = $defaultInstructor?->display_name;

        $nextLessons = $this->nextLessonsByLearner($orgId, $learnerIds, $nowUtc);
        $instructorNames = $this->instructorNamesById(
            array_values(array_unique(array_filter(array_map(
                static fn (array $row): int => (int) ($row['instructor_id'] ?? 0),
                $nextLessons,
            )))),
        );
        $progressById = $this->progressPercentByLearner($orgId, $learnerIds);
        $completedById = $this->completedLessonCountsByLearner($orgId, $learnerIds);

        $enriched = [];
        foreach ($items as $item) {
            $id = (int) $item['id'];
            $next = $nextLessons[$id] ?? null;
            $instructorId = $next !== null ? (int) ($next['instructor_id'] ?? 0) : 0;
            if ($instructorId <= 0 && $defaultInstructor !== null) {
                $instructorId = (int) $defaultInstructor->id;
            }

            $item['instructor_id'] = $instructorId > 0 ? $instructorId : null;
            $item['instructor_name'] = $instructorNames[$instructorId]
                ?? $defaultInstructorName
                ?? '—';
            $item['lesson_label'] = $next !== null
                ? $this->formatLessonLabel((string) $next['starts_at'], $tz)
                : 'Not booked';
            $item['lesson_booked'] = $next !== null;
            $item['progress_percent'] = $progressById[$id] ?? 0;
            $item['lessons_completed'] = $completedById[$id] ?? 0;
            $item['theory_label'] = $this->theoryLabel($item['theory_status'] ?? null);
            $item['practical_label'] = $this->practicalLabel(
                $item['status'] ?? ($item['lifecycle'] ?? null),
                $item['test_date'] ?? null,
                $tz,
                $nowUtc,
            );
            $enriched[] = $item;
        }

        return $this->enrichAvailabilityContext($enriched, $nowUtc);
    }

    /**
     * @param list<int> $learnerIds
     * @return array<int, array{starts_at: string, instructor_id: int}>
     */
    private function nextLessonsByLearner(int $orgId, array $learnerIds, DateTimeImmutable $nowUtc): array
    {
        if ($learnerIds === []) {
            return [];
        }

        $nowSql = $nowUtc->format('Y-m-d H:i:s');
        $rows = Lesson::find()
            ->select(['learner_id', 'starts_at', 'instructor_id'])
            ->andWhere([
                'organisation_id' => $orgId,
                'status' => Lesson::STATUS_SCHEDULED,
                'learner_id' => $learnerIds,
            ])
            ->andWhere(['>=', 'starts_at', $nowSql])
            ->orderBy(['learner_id' => SORT_ASC, 'starts_at' => SORT_ASC])
            ->asArray()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $lid = (int) $row['learner_id'];
            if (isset($map[$lid])) {
                continue;
            }
            $map[$lid] = [
                'starts_at' => (string) $row['starts_at'],
                'instructor_id' => (int) $row['instructor_id'],
            ];
        }

        return $map;
    }

    /**
     * @param list<int> $instructorIds
     * @return array<int, string>
     */
    private function instructorNamesById(array $instructorIds): array
    {
        if ($instructorIds === []) {
            return [];
        }

        $rows = Instructor::find()
            ->select(['id', 'display_name'])
            ->andWhere(['id' => $instructorIds])
            ->asArray()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = (string) $row['display_name'];
        }

        return $map;
    }

    /**
     * @param list<int> $learnerIds
     * @return array<int, int>
     */
    private function progressPercentByLearner(int $orgId, array $learnerIds): array
    {
        if ($learnerIds === []) {
            return [];
        }

        $total = (int) ProgressSkill::find()->andWhere(['active' => true])->count();
        if ($total <= 0) {
            return [];
        }

        $rows = LearnerSkillProgress::find()
            ->select([
                'learner_id',
                'COUNT(DISTINCT [[skill_id]]) AS rated',
            ])
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerIds,
            ])
            ->groupBy(['learner_id'])
            ->asArray()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $rated = (int) $row['rated'];
            $percent = (int) round(($rated / $total) * 100);
            if ($percent > 100) {
                $percent = 100;
            }
            $map[(int) $row['learner_id']] = $percent;
        }

        return $map;
    }

    /**
     * @param list<int> $learnerIds
     * @return array<int, int>
     */
    private function completedLessonCountsByLearner(int $orgId, array $learnerIds): array
    {
        if ($learnerIds === []) {
            return [];
        }

        $rows = Lesson::find()
            ->select([
                'learner_id',
                'COUNT(*) AS cnt',
            ])
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerIds,
                'status' => Lesson::STATUS_COMPLETED,
            ])
            ->groupBy(['learner_id'])
            ->asArray()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['learner_id']] = (int) $row['cnt'];
        }

        return $map;
    }

    private function formatLessonLabel(string $startsAtUtc, DateTimeZone $tz): string
    {
        try {
            $local = (new DateTimeImmutable($startsAtUtc, new DateTimeZone('UTC')))->setTimezone($tz);
        } catch (\Exception) {
            return 'Booked';
        }

        return $local->format('j M · H:i');
    }

    private function theoryLabel(mixed $theoryStatus): string
    {
        return match ($theoryStatus) {
            'passed' => 'Passed',
            'booked' => 'Booked',
            default => 'No test',
        };
    }

    private function practicalLabel(
        mixed $status,
        mixed $testDate,
        DateTimeZone $tz,
        DateTimeImmutable $nowUtc,
    ): string {
        if ($status === Learner::STATUS_PASSED || $status === Learner::LIFECYCLE_PASSED) {
            return 'Passed';
        }
        if (!is_string($testDate) || $testDate === '') {
            return 'No test';
        }

        try {
            $date = new DateTimeImmutable($testDate . ' 12:00:00', $tz);
        } catch (\Exception) {
            return 'Booked';
        }

        $today = $nowUtc->setTimezone($tz)->setTime(0, 0, 0);
        if ($date < $today) {
            return $date->format('j M Y');
        }

        return $date->format('j M Y');
    }

    private function countTestsSoon(DateTimeImmutable $nowUtc): int
    {
        return count($this->testSoonLearnerIds($nowUtc));
    }

    /**
     * @return array<int, true>
     */
    private function testSoonLearnerIds(DateTimeImmutable $nowUtc): array
    {
        $org = $this->requireOrganisation();
        $tz = OrganisationTime::timezoneFor($org);
        $todayLocal = $nowUtc->setTimezone($tz)->setTime(0, 0, 0);
        $horizonEnd = $todayLocal->modify('+' . self::TEST_HORIZON_DAYS . ' days')->format('Y-m-d');
        $today = $todayLocal->format('Y-m-d');

        $ids = TenantContext::scopeByOrganisation(Learner::find())
            ->select(['id'])
            ->andWhere(['archived_at' => null])
            ->andWhere(['lifecycle' => Learner::LIFECYCLE_ACTIVE])
            ->andWhere(['not', ['test_date' => null]])
            ->andWhere(['between', 'test_date', $today, $horizonEnd])
            ->column();

        $map = [];
        foreach ($ids as $id) {
            $map[(int) $id] = true;
        }

        return $map;
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
