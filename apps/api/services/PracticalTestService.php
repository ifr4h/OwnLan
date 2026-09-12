<?php

declare(strict_types=1);

namespace app\services;

use app\components\MockFaultCatalogue;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\MockTest;
use app\models\MockTestFault;
use app\models\Organisation;
use app\models\PracticalTest;
use app\models\PracticalTestFault;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Official practical test results + rolling fault trends (DVSA-style ADI report).
 */
class PracticalTestService
{
    /**
     * @return array<string, mixed>
     */
    public function catalogue(): array
    {
        return [
            'fault_types' => [
                ['code' => PracticalTestFault::TYPE_DRIVING, 'label' => 'Driving fault'],
                ['code' => PracticalTestFault::TYPE_SERIOUS, 'label' => 'Serious fault'],
                ['code' => PracticalTestFault::TYPE_DANGEROUS, 'label' => 'Dangerous fault'],
            ],
            'areas' => MockFaultCatalogue::groupedByArea(),
            'result_rules' => [
                'pass' => '15 or fewer driving faults, no serious or dangerous faults',
                'note' => 'Official DVSA practical test result logged by you — not scraped from DVSA.',
            ],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(?string $from = null, ?string $to = null, ?int $learnerId = null, int $limit = 100): array
    {
        $orgId = TenantContext::requireOrganisationId();
        [$fromDate, $toDate] = $this->resolveRange($from, $to);

        $query = PracticalTest::find()
            ->andWhere(['organisation_id' => $orgId])
            ->andWhere(['>=', 'test_date', $fromDate])
            ->andWhere(['<=', 'test_date', $toDate])
            ->orderBy(['test_date' => SORT_DESC, 'id' => SORT_DESC]);

        if ($learnerId !== null) {
            $this->findOwnedLearner($learnerId);
            $query->andWhere(['learner_id' => $learnerId]);
        }

        $total = (int) (clone $query)->count();
        /** @var PracticalTest[] $rows */
        $rows = $query->limit(max(1, min(500, $limit)))->all();

        return [
            'items' => array_map(fn (PracticalTest $t) => $this->serialize($t), $rows),
            'total' => $total,
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listForLearner(int $learnerId): array
    {
        $this->findOwnedLearner($learnerId);
        $orgId = TenantContext::requireOrganisationId();

        $query = PracticalTest::find()
            ->andWhere(['organisation_id' => $orgId, 'learner_id' => $learnerId])
            ->orderBy(['test_date' => SORT_DESC, 'id' => SORT_DESC]);

        /** @var PracticalTest[] $rows */
        $rows = $query->all();

        return [
            'items' => array_map(fn (PracticalTest $t) => $this->serialize($t), $rows),
            'total' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function view(int $id): array
    {
        return $this->serialize($this->findOwned($id), detailed: true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $instructorId = $this->currentInstructorId();
        $learnerId = (int) ($data['learner_id'] ?? 0);
        $learner = $this->findOwnedLearner($learnerId);

        $test = new PracticalTest();
        $test->organisation_id = $orgId;
        $test->instructor_id = $instructorId;
        $test->learner_id = $learnerId;
        $this->applyHeader($test, $data, $learner);
        $now = gmdate('Y-m-d H:i:s');
        $test->created_at = $now;
        $test->updated_at = $now;

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$test->save()) {
                throw new BadRequestHttpException('Could not save that test result.');
            }
            $this->replaceFaults($test, $data['faults'] ?? []);
            $this->recount($test);
            if (!$test->save(true, [
                'driving_faults_count',
                'serious_faults_count',
                'dangerous_faults_count',
                'updated_at',
            ])) {
                throw new BadRequestHttpException('Could not save fault totals.');
            }
            $this->maybeMarkPassed($learner, $test, $data);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return $this->serialize($test, detailed: true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $test = $this->findOwned($id);
        $learner = $this->findOwnedLearner((int) $test->learner_id);
        $this->applyHeader($test, $data, $learner);
        $test->updated_at = gmdate('Y-m-d H:i:s');

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$test->save()) {
                throw new BadRequestHttpException('Could not update that test result.');
            }
            if (array_key_exists('faults', $data)) {
                $this->replaceFaults($test, $data['faults'] ?? []);
                $this->recount($test);
                if (!$test->save(true, [
                    'driving_faults_count',
                    'serious_faults_count',
                    'dangerous_faults_count',
                    'updated_at',
                ])) {
                    throw new BadRequestHttpException('Could not save fault totals.');
                }
            }
            $this->maybeMarkPassed($learner, $test, $data);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return $this->serialize($test, detailed: true);
    }

    /**
     * @return array{ok: true}
     */
    public function delete(int $id): array
    {
        $test = $this->findOwned($id);
        PracticalTestFault::deleteAll(['practical_test_id' => (int) $test->id]);
        $test->delete();

        return ['ok' => true];
    }

    /**
     * Rolling ADI-style fault analysis for the organisation.
     *
     * @return array<string, mixed>
     */
    public function stats(?string $from = null, ?string $to = null): array
    {
        $orgId = TenantContext::requireOrganisationId();
        [$fromDate, $toDate] = $this->resolveRange($from, $to);

        /** @var PracticalTest[] $tests */
        $tests = PracticalTest::find()
            ->andWhere(['organisation_id' => $orgId])
            ->andWhere(['>=', 'test_date', $fromDate])
            ->andWhere(['<=', 'test_date', $toDate])
            ->orderBy(['test_date' => SORT_ASC])
            ->all();

        $testsTaken = count($tests);
        $testsPassed = 0;
        $accompanied = 0;
        $examinerActions = 0;
        $drivingTotal = 0;
        $seriousTotal = 0;
        $dangerousTotal = 0;
        $pupilIds = [];

        foreach ($tests as $test) {
            $pupilIds[(int) $test->learner_id] = true;
            if ($test->result === PracticalTest::RESULT_PASS) {
                $testsPassed++;
            }
            if ($test->accompanied) {
                $accompanied++;
            }
            if ($test->examiner_action) {
                $examinerActions++;
            }
            $drivingTotal += (int) $test->driving_faults_count;
            $seriousTotal += (int) $test->serious_faults_count;
            $dangerousTotal += (int) $test->dangerous_faults_count;
        }

        $passRate = $testsTaken > 0 ? round(($testsPassed / $testsTaken) * 100, 1) : null;
        $avgDriving = $testsTaken > 0 ? round($drivingTotal / $testsTaken, 2) : null;
        $avgSerious = $testsTaken > 0 ? round($seriousTotal / $testsTaken, 2) : null;
        $avgDangerous = $testsTaken > 0 ? round($dangerousTotal / $testsTaken, 2) : null;
        $examinerActionPct = $testsTaken > 0 ? round(($examinerActions / $testsTaken) * 100, 1) : null;

        $faultBreakdown = $this->faultBreakdown($orgId, $fromDate, $toDate);
        $areaBreakdown = $this->areaBreakdown($faultBreakdown);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'range_label' => $this->rangeLabel($fromDate, $toDate),
            'summary' => [
                'pupils_tested' => count($pupilIds),
                'tests_taken' => $testsTaken,
                'tests_passed' => $testsPassed,
                'tests_failed' => $testsTaken - $testsPassed,
                'pass_rate_percent' => $passRate,
                'pass_rate_label' => $passRate === null ? null : rtrim(rtrim(number_format($passRate, 1), '0'), '.') . '%',
                'accompanied_count' => $accompanied,
                'examiner_action_count' => $examinerActions,
                'examiner_action_percent' => $examinerActionPct,
                'avg_driving_faults' => $avgDriving,
                'avg_serious_faults' => $avgSerious,
                'avg_dangerous_faults' => $avgDangerous,
                'total_driving_faults' => $drivingTotal,
                'total_serious_faults' => $seriousTotal,
                'total_dangerous_faults' => $dangerousTotal,
            ],
            'indicators' => $this->dvsaIndicators($avgDriving, $avgSerious, $examinerActionPct, $passRate),
            'faults_by_category' => $faultBreakdown,
            'faults_by_area' => $areaBreakdown,
            'top_faults' => array_slice($faultBreakdown, 0, 8),
            'recent_tests' => array_map(
                fn (PracticalTest $t) => $this->serialize($t),
                array_slice(array_reverse($tests), 0, 12),
            ),
            'mock_comparison' => $this->mockComparison($orgId, $fromDate, $toDate),
            'empty' => $testsTaken === 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyHeader(PracticalTest $test, array $data, Learner $learner): void
    {
        $result = (string) ($data['result'] ?? $test->result ?? '');
        if (!in_array($result, PracticalTest::RESULTS, true)) {
            throw new BadRequestHttpException('Result must be pass or fail.');
        }
        $testDate = trim((string) ($data['test_date'] ?? $test->test_date ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $testDate)) {
            throw new BadRequestHttpException('Test date must be YYYY-MM-DD.');
        }

        $centre = trim((string) ($data['test_centre'] ?? $test->test_centre ?? $learner->test_centre ?? ''));
        $notes = array_key_exists('instructor_notes', $data)
            ? trim((string) $data['instructor_notes'])
            : (string) ($test->instructor_notes ?? '');

        $test->test_date = $testDate;
        $test->result = $result;
        $test->test_centre = $centre !== '' ? mb_substr($centre, 0, 255) : null;
        $test->accompanied = array_key_exists('accompanied', $data)
            ? (bool) $data['accompanied']
            : ($test->isNewRecord ? true : (bool) $test->accompanied);
        $test->examiner_action = array_key_exists('examiner_action', $data)
            ? (bool) $data['examiner_action']
            : ($test->isNewRecord ? false : (bool) $test->examiner_action);
        $test->instructor_notes = $notes !== '' ? $notes : null;
    }

    /**
     * @param mixed $faultsRaw
     */
    private function replaceFaults(PracticalTest $test, mixed $faultsRaw): void
    {
        PracticalTestFault::deleteAll(['practical_test_id' => (int) $test->id]);
        if (!is_array($faultsRaw)) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $orgId = (int) $test->organisation_id;

        foreach ($faultsRaw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['fault_code'] ?? ''));
            $type = trim((string) ($row['fault_type'] ?? ''));
            $count = max(1, min(99, (int) ($row['count'] ?? 1)));
            if ($code === '' || !in_array($type, PracticalTestFault::TYPES, true)) {
                continue;
            }
            $item = MockFaultCatalogue::find($code);
            if ($item === null) {
                throw new BadRequestHttpException('Unknown fault code: ' . $code);
            }

            $fault = new PracticalTestFault();
            $fault->organisation_id = $orgId;
            $fault->practical_test_id = (int) $test->id;
            $fault->fault_type = $type;
            $fault->fault_code = $code;
            $fault->fault_label = $item['label'];
            $fault->area = $item['area'];
            $fault->aspect = $item['aspect'];
            $fault->skill_code = $item['skill_code'];
            $fault->count = $type === PracticalTestFault::TYPE_DRIVING ? $count : 1;
            $fault->created_at = $now;
            if (!$fault->save()) {
                throw new BadRequestHttpException('Could not save a fault tick.');
            }
        }
    }

    private function recount(PracticalTest $test): void
    {
        $driving = (int) PracticalTestFault::find()
            ->andWhere([
                'practical_test_id' => (int) $test->id,
                'fault_type' => PracticalTestFault::TYPE_DRIVING,
            ])
            ->sum('count');
        $serious = (int) PracticalTestFault::find()
            ->andWhere([
                'practical_test_id' => (int) $test->id,
                'fault_type' => PracticalTestFault::TYPE_SERIOUS,
            ])
            ->sum('count');
        $dangerous = (int) PracticalTestFault::find()
            ->andWhere([
                'practical_test_id' => (int) $test->id,
                'fault_type' => PracticalTestFault::TYPE_DANGEROUS,
            ])
            ->sum('count');

        $test->driving_faults_count = $driving;
        $test->serious_faults_count = $serious;
        $test->dangerous_faults_count = $dangerous;
        $test->updated_at = gmdate('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function maybeMarkPassed(Learner $learner, PracticalTest $test, array $data): void
    {
        if ($test->result !== PracticalTest::RESULT_PASS) {
            return;
        }
        if (!(bool) ($data['mark_learner_passed'] ?? false)) {
            return;
        }
        if ($learner->lifecycle === Learner::LIFECYCLE_PASSED) {
            return;
        }
        $learner->lifecycle = Learner::LIFECYCLE_PASSED;
        $learner->waiting_list_joined_at = null;
        $learner->archived_at = null;
        $learner->updated_at = gmdate('Y-m-d H:i:s');
        $learner->save(true, ['lifecycle', 'waiting_list_joined_at', 'archived_at', 'updated_at']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function faultBreakdown(int $orgId, string $fromDate, string $toDate): array
    {
        $sql = <<<'SQL'
SELECT f.fault_code, f.fault_label, f.area, f.aspect, f.skill_code, f.fault_type, SUM(f.count) AS total
FROM practical_test_faults f
INNER JOIN practical_tests t ON t.id = f.practical_test_id
WHERE t.organisation_id = :org
  AND t.test_date >= :from
  AND t.test_date <= :to
GROUP BY f.fault_code, f.fault_label, f.area, f.aspect, f.skill_code, f.fault_type
ORDER BY total DESC, f.fault_label ASC
SQL;
        /** @var list<array<string, mixed>> $rows */
        $rows = Yii::$app->db->createCommand($sql, [
            ':org' => $orgId,
            ':from' => $fromDate,
            ':to' => $toDate,
        ])->queryAll();

        $grouped = [];
        foreach ($rows as $row) {
            $code = (string) $row['fault_code'];
            if (!isset($grouped[$code])) {
                $grouped[$code] = [
                    'fault_code' => $code,
                    'fault_label' => (string) $row['fault_label'],
                    'area' => (string) $row['area'],
                    'aspect' => (string) $row['aspect'],
                    'skill_code' => $row['skill_code'] !== null ? (string) $row['skill_code'] : null,
                    'driving' => 0,
                    'serious' => 0,
                    'dangerous' => 0,
                    'total' => 0,
                ];
            }
            $n = (int) $row['total'];
            $type = (string) $row['fault_type'];
            if ($type === PracticalTestFault::TYPE_DRIVING) {
                $grouped[$code]['driving'] += $n;
            } elseif ($type === PracticalTestFault::TYPE_SERIOUS) {
                $grouped[$code]['serious'] += $n;
            } elseif ($type === PracticalTestFault::TYPE_DANGEROUS) {
                $grouped[$code]['dangerous'] += $n;
            }
            $grouped[$code]['total'] += $n;
        }

        $list = array_values($grouped);
        usort($list, static fn (array $a, array $b) => $b['total'] <=> $a['total'] ?: strcmp($a['fault_label'], $b['fault_label']));

        $max = 0;
        foreach ($list as $item) {
            $max = max($max, (int) $item['total']);
        }
        foreach ($list as &$item) {
            $item['bar_percent'] = $max > 0 ? (int) round(((int) $item['total'] / $max) * 100) : 0;
        }
        unset($item);

        return $list;
    }

    /**
     * @param list<array<string, mixed>> $faultBreakdown
     * @return list<array<string, mixed>>
     */
    private function areaBreakdown(array $faultBreakdown): array
    {
        $areas = [];
        foreach ($faultBreakdown as $item) {
            $area = (string) $item['area'];
            if (!isset($areas[$area])) {
                $areas[$area] = [
                    'area' => $area,
                    'driving' => 0,
                    'serious' => 0,
                    'dangerous' => 0,
                    'total' => 0,
                ];
            }
            $areas[$area]['driving'] += (int) $item['driving'];
            $areas[$area]['serious'] += (int) $item['serious'];
            $areas[$area]['dangerous'] += (int) $item['dangerous'];
            $areas[$area]['total'] += (int) $item['total'];
        }
        $list = array_values($areas);
        usort($list, static fn (array $a, array $b) => $b['total'] <=> $a['total']);

        $max = 0;
        foreach ($list as $item) {
            $max = max($max, (int) $item['total']);
        }
        foreach ($list as &$item) {
            $item['bar_percent'] = $max > 0 ? (int) round(((int) $item['total'] / $max) * 100) : 0;
        }
        unset($item);

        return $list;
    }

    /**
     * @return array{items: list<array<string, mixed>>, note: string}
     */
    private function mockComparison(int $orgId, string $fromDate, string $toDate): array
    {
        $fromUtc = $fromDate . ' 00:00:00';
        $toUtc = (new DateTimeImmutable($toDate, new DateTimeZone('UTC')))
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');

        $sql = <<<'SQL'
SELECT f.fault_code, f.fault_label, f.fault_type, COUNT(*) AS total
FROM mock_test_faults f
INNER JOIN mock_tests m ON m.id = f.mock_test_id
WHERE m.organisation_id = :org
  AND m.status = :status
  AND f.undone_at IS NULL
  AND m.finished_at >= :from
  AND m.finished_at < :to
GROUP BY f.fault_code, f.fault_label, f.fault_type
ORDER BY total DESC
LIMIT 20
SQL;
        /** @var list<array<string, mixed>> $rows */
        $rows = Yii::$app->db->createCommand($sql, [
            ':org' => $orgId,
            ':status' => MockTest::STATUS_COMPLETED,
            ':from' => $fromUtc,
            ':to' => $toUtc,
        ])->queryAll();

        $grouped = [];
        foreach ($rows as $row) {
            $code = (string) $row['fault_code'];
            if (!isset($grouped[$code])) {
                $item = MockFaultCatalogue::find($code);
                $grouped[$code] = [
                    'fault_code' => $code,
                    'fault_label' => (string) $row['fault_label'],
                    'area' => $item['area'] ?? '',
                    'driving' => 0,
                    'serious' => 0,
                    'dangerous' => 0,
                    'total' => 0,
                ];
            }
            $n = (int) $row['total'];
            $type = (string) $row['fault_type'];
            if ($type === MockTestFault::TYPE_DRIVING) {
                $grouped[$code]['driving'] += $n;
            } elseif ($type === MockTestFault::TYPE_SERIOUS) {
                $grouped[$code]['serious'] += $n;
            } elseif ($type === MockTestFault::TYPE_DANGEROUS) {
                $grouped[$code]['dangerous'] += $n;
            }
            $grouped[$code]['total'] += $n;
        }

        $list = array_values($grouped);
        usort($list, static fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return [
            'items' => array_slice($list, 0, 8),
            'note' => 'Mock faults from the same window — useful for spotting the same patterns before test day.',
        ];
    }

    /**
     * Informational DVSA-style indicators (not a prediction or official score).
     *
     * @return list<array<string, mixed>>
     */
    private function dvsaIndicators(
        ?float $avgDriving,
        ?float $avgSerious,
        ?float $examinerActionPct,
        ?float $passRate,
    ): array {
        return [
            [
                'key' => 'avg_driving',
                'label' => 'Avg driving faults',
                'value' => $avgDriving,
                'value_label' => $avgDriving === null ? '—' : number_format($avgDriving, 2),
                'trigger' => '6 or more',
                'triggered' => $avgDriving !== null && $avgDriving >= 6,
            ],
            [
                'key' => 'avg_serious',
                'label' => 'Avg serious faults',
                'value' => $avgSerious,
                'value_label' => $avgSerious === null ? '—' : number_format($avgSerious, 2),
                'trigger' => '0.55 or more',
                'triggered' => $avgSerious !== null && $avgSerious >= 0.55,
            ],
            [
                'key' => 'examiner_action',
                'label' => 'Examiner took action',
                'value' => $examinerActionPct,
                'value_label' => $examinerActionPct === null ? '—' : rtrim(rtrim(number_format($examinerActionPct, 1), '0'), '.') . '%',
                'trigger' => '10% or higher',
                'triggered' => $examinerActionPct !== null && $examinerActionPct >= 10,
            ],
            [
                'key' => 'pass_rate',
                'label' => 'Pass rate',
                'value' => $passRate,
                'value_label' => $passRate === null ? '—' : rtrim(rtrim(number_format($passRate, 1), '0'), '.') . '%',
                'trigger' => '55% or lower',
                'triggered' => $passRate !== null && $passRate <= 55,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(?string $from, ?string $to): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var Organisation|null $org */
        $org = Organisation::findOne($orgId);
        if ($org === null) {
            throw new ForbiddenHttpException('Organisation not found.');
        }
        $tz = OrganisationTime::timezoneFor($org);
        $today = new DateTimeImmutable('now', $tz);
        $defaultTo = $today->format('Y-m-d');
        $defaultFrom = $today->sub(new DateInterval('P365D'))->format('Y-m-d');

        $fromDate = trim((string) $from);
        $toDate = trim((string) $to);
        if ($fromDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $fromDate = $defaultFrom;
        }
        if ($toDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            $toDate = $defaultTo;
        }
        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    private function rangeLabel(string $from, string $to): string
    {
        $fromTs = strtotime($from . ' UTC');
        $toTs = strtotime($to . ' UTC');
        if ($fromTs === false || $toTs === false) {
            return $from . ' – ' . $to;
        }

        return gmdate('j M Y', $fromTs) . ' – ' . gmdate('j M Y', $toTs);
    }

    private function findOwned(int $id): PracticalTest
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var PracticalTest|null $test */
        $test = PracticalTest::find()
            ->andWhere(['id' => $id, 'organisation_id' => $orgId])
            ->one();
        if ($test === null) {
            throw new NotFoundHttpException('Test result not found.');
        }

        return $test;
    }

    private function findOwnedLearner(int $learnerId): Learner
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var Learner|null $learner */
        $learner = Learner::find()
            ->andWhere(['id' => $learnerId, 'organisation_id' => $orgId])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }

    private function currentInstructorId(): int
    {
        $userId = (int) Yii::$app->user->id;
        $orgId = TenantContext::requireOrganisationId();
        $instructorId = Yii::$app->db->createCommand(
            'SELECT id FROM instructors WHERE user_id = :uid AND organisation_id = :org LIMIT 1',
            [':uid' => $userId, ':org' => $orgId],
        )->queryScalar();
        if ($instructorId === false) {
            throw new ForbiddenHttpException('Instructor profile not found.');
        }

        return (int) $instructorId;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PracticalTest $test, bool $detailed = false): array
    {
        $learner = $test->learner;
        $payload = [
            'id' => (int) $test->id,
            'learner_id' => (int) $test->learner_id,
            'learner_first_name' => $learner?->first_name,
            'learner_name' => $learner?->fullName,
            'test_date' => $test->test_date,
            'date_display' => $this->formatDate($test->test_date),
            'result' => $test->result,
            'result_label' => $test->result === PracticalTest::RESULT_PASS ? 'Pass' : 'Fail',
            'test_centre' => $test->test_centre,
            'accompanied' => (bool) $test->accompanied,
            'examiner_action' => (bool) $test->examiner_action,
            'driving_faults_count' => (int) $test->driving_faults_count,
            'serious_faults_count' => (int) $test->serious_faults_count,
            'dangerous_faults_count' => (int) $test->dangerous_faults_count,
            'instructor_notes' => $test->instructor_notes,
        ];

        if ($detailed) {
            /** @var PracticalTestFault[] $faults */
            $faults = PracticalTestFault::find()
                ->andWhere(['practical_test_id' => (int) $test->id])
                ->orderBy(['area' => SORT_ASC, 'aspect' => SORT_ASC])
                ->all();
            $payload['faults'] = array_map(static fn (PracticalTestFault $f) => [
                'id' => (int) $f->id,
                'fault_type' => $f->fault_type,
                'fault_code' => $f->fault_code,
                'fault_label' => $f->fault_label,
                'area' => $f->area,
                'aspect' => $f->aspect,
                'skill_code' => $f->skill_code,
                'count' => (int) $f->count,
            ], $faults);
        }

        return $payload;
    }

    private function formatDate(string $date): string
    {
        $ts = strtotime($date . ' UTC');

        return $ts !== false ? gmdate('j M Y', $ts) : $date;
    }
}
