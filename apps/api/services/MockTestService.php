<?php

declare(strict_types=1);

namespace app\services;

use app\components\MockFaultCatalogue;
use app\components\MockTestResultRules;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\Lesson;
use app\models\MockTest;
use app\models\MockTestFault;
use app\models\ProgressSkill;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Mock Test Studio — conduct, record faults, review, and connect to progress.
 *
 * Mock faults become skill evidence but never auto-change instructor assessments.
 */
class MockTestService
{
    /**
     * @return array<string, mixed>
     */
    public function catalogue(): array
    {
        return [
            'fault_types' => [
                ['code' => MockTestFault::TYPE_DRIVING, 'label' => 'Driving fault'],
                ['code' => MockTestFault::TYPE_SERIOUS, 'label' => 'Serious fault'],
                ['code' => MockTestFault::TYPE_DANGEROUS, 'label' => 'Dangerous fault'],
            ],
            'areas' => MockFaultCatalogue::groupedByArea(),
            'result_rules' => [
                'pass_standard' => '15 or fewer driving faults, no serious or dangerous faults',
                'note' => 'Mock test result — not an official DVSA test.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function startForLesson(int $lessonId, ?string $clientSessionId = null): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var Lesson|null $lesson */
        $lesson = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['id' => $lessonId])
            ->one();
        if ($lesson === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }
        if ($lesson->status === Lesson::STATUS_CANCELLED || $lesson->status === Lesson::STATUS_NO_SHOW) {
            throw new BadRequestHttpException('Cannot start a mock on this lesson.');
        }

        $existing = MockTest::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'lesson_id' => $lessonId,
                'status' => MockTest::STATUS_IN_PROGRESS,
            ])
            ->one();
        if ($existing instanceof MockTest) {
            return $this->serialize($existing, detailed: true);
        }

        if ($clientSessionId !== null && $clientSessionId !== '') {
            $byClient = MockTest::find()
                ->andWhere([
                    'organisation_id' => $orgId,
                    'client_session_id' => $clientSessionId,
                ])
                ->one();
            if ($byClient instanceof MockTest) {
                return $this->serialize($byClient, detailed: true);
            }
        }

        $instructorId = $this->currentInstructorId();
        $now = gmdate('Y-m-d H:i:s');
        $mock = new MockTest();
        $mock->organisation_id = $orgId;
        $mock->instructor_id = $instructorId;
        $mock->learner_id = (int) $lesson->learner_id;
        $mock->lesson_id = (int) $lesson->id;
        $mock->status = MockTest::STATUS_IN_PROGRESS;
        $mock->started_at = $now;
        $mock->client_session_id = $clientSessionId;
        $mock->created_at = $now;
        $mock->updated_at = $now;
        if (!$mock->save()) {
            throw new BadRequestHttpException('Could not start mock test.');
        }

        return $this->serialize($mock, detailed: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function startForLearner(int $learnerId, ?string $clientSessionId = null): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        $active = MockTest::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerId,
                'status' => MockTest::STATUS_IN_PROGRESS,
            ])
            ->one();
        if ($active instanceof MockTest) {
            return $this->serialize($active, detailed: true);
        }

        $lesson = $this->findAssociableLesson($learner);
        if ($lesson !== null) {
            return $this->startForLesson((int) $lesson->id, $clientSessionId);
        }

        if ($clientSessionId !== null && $clientSessionId !== '') {
            $byClient = MockTest::find()
                ->andWhere([
                    'organisation_id' => $orgId,
                    'client_session_id' => $clientSessionId,
                ])
                ->one();
            if ($byClient instanceof MockTest) {
                return $this->serialize($byClient, detailed: true);
            }
        }

        $instructorId = $this->currentInstructorId();
        $now = gmdate('Y-m-d H:i:s');
        $mock = new MockTest();
        $mock->organisation_id = $orgId;
        $mock->instructor_id = $instructorId;
        $mock->learner_id = $learnerId;
        $mock->lesson_id = null;
        $mock->status = MockTest::STATUS_IN_PROGRESS;
        $mock->started_at = $now;
        $mock->client_session_id = $clientSessionId;
        $mock->created_at = $now;
        $mock->updated_at = $now;
        if (!$mock->save()) {
            throw new BadRequestHttpException('Could not start mock test.');
        }

        return $this->serialize($mock, detailed: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function view(int $mockId, bool $learnerVisible = false): array
    {
        $mock = $this->findMockOrFail($mockId);
        if ($learnerVisible) {
            $this->assertLearnerCanView($mock);
        }

        return $this->serialize($mock, detailed: true, learnerVisible: $learnerVisible);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordFault(int $mockId, array $data): array
    {
        $mock = $this->findMockOrFail($mockId);
        if (!$mock->isActive()) {
            throw new BadRequestHttpException('This mock test is no longer active.');
        }

        $clientOpId = trim((string) ($data['client_op_id'] ?? ''));
        if ($clientOpId !== '') {
            $existing = MockTestFault::find()
                ->andWhere(['mock_test_id' => (int) $mock->id, 'client_op_id' => $clientOpId])
                ->one();
            if ($existing instanceof MockTestFault && $existing->undone_at === null) {
                return $this->serializeFault($existing);
            }
        }

        $faultType = (string) ($data['fault_type'] ?? '');
        if (!in_array($faultType, MockTestFault::TYPES, true)) {
            throw new BadRequestHttpException('Invalid fault type.');
        }

        $faultCode = trim((string) ($data['fault_code'] ?? ''));
        $catalogueItem = MockFaultCatalogue::find($faultCode);
        if ($catalogueItem === null) {
            throw new BadRequestHttpException('Invalid fault category.');
        }

        $skill = ProgressSkill::findOne(['code' => $catalogueItem['skill_code'], 'active' => true]);
        if ($skill === null) {
            throw new BadRequestHttpException('Skill mapping not found.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $elapsed = $this->elapsedSeconds($mock, $now);

        $fault = new MockTestFault();
        $fault->organisation_id = (int) $mock->organisation_id;
        $fault->mock_test_id = (int) $mock->id;
        $fault->skill_id = (int) $skill->id;
        $fault->fault_type = $faultType;
        $fault->fault_code = $faultCode;
        $fault->fault_label = $catalogueItem['label'];
        $fault->note = trim((string) ($data['note'] ?? '')) ?: null;
        $fault->recorded_at = $now;
        $fault->elapsed_seconds = $elapsed;
        $fault->route_moment_id = isset($data['route_moment_id']) ? (int) $data['route_moment_id'] : null;
        $fault->client_op_id = $clientOpId !== '' ? $clientOpId : null;
        $fault->created_at = $now;
        if (!$fault->save()) {
            throw new BadRequestHttpException('Could not record fault.');
        }

        $this->recountFaults($mock);

        return $this->serializeFault($fault);
    }

    /**
     * @return array<string, mixed>
     */
    public function undoFault(int $mockId, int $faultId): array
    {
        $mock = $this->findMockOrFail($mockId);
        if (!$mock->isActive()) {
            throw new BadRequestHttpException('This mock test is no longer active.');
        }

        $fault = MockTestFault::findOne([
            'id' => $faultId,
            'mock_test_id' => (int) $mock->id,
            'organisation_id' => (int) $mock->organisation_id,
        ]);
        if ($fault === null || $fault->undone_at !== null) {
            throw new NotFoundHttpException('Fault not found.');
        }

        $fault->undone_at = gmdate('Y-m-d H:i:s');
        $fault->save(false, ['undone_at']);
        $this->recountFaults($mock);

        return ['status' => 'ok'];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function finish(int $mockId, array $data): array
    {
        $mock = $this->findMockOrFail($mockId);
        if (!$mock->isActive()) {
            throw new BadRequestHttpException('This mock test is no longer active.');
        }

        $result = MockTestResultRules::calculate(
            (int) $mock->driving_faults_count,
            (int) $mock->serious_faults_count,
            (int) $mock->dangerous_faults_count,
        );

        $now = gmdate('Y-m-d H:i:s');
        $mock->status = MockTest::STATUS_COMPLETED;
        $mock->result = $result['result'];
        $mock->finished_at = $now;
        $mock->learner_summary = trim((string) ($data['learner_summary'] ?? '')) ?: $this->buildLearnerSummary($mock);
        $mock->instructor_note = trim((string) ($data['instructor_note'] ?? '')) ?: null;
        $mock->private_note = trim((string) ($data['private_note'] ?? '')) ?: null;
        $mock->suggested_next_focus = trim((string) ($data['suggested_next_focus'] ?? '')) ?: null;
        $mock->updated_at = $now;
        if (!$mock->save()) {
            throw new BadRequestHttpException('Could not finish mock test.');
        }

        $payload = $this->serialize($mock, detailed: true);
        $payload['suggested_next_focus_options'] = $this->suggestNextFocus($mock);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function abandon(int $mockId): array
    {
        $mock = $this->findMockOrFail($mockId);
        if (!$mock->isActive()) {
            throw new BadRequestHttpException('This mock test is no longer active.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $mock->status = MockTest::STATUS_ABANDONED;
        $mock->finished_at = $now;
        $mock->updated_at = $now;
        $mock->save(false, ['status', 'finished_at', 'updated_at']);

        return $this->serialize($mock, detailed: true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateCompleted(int $mockId, array $data): array
    {
        $mock = $this->findMockOrFail($mockId);
        if ($mock->status !== MockTest::STATUS_COMPLETED) {
            throw new BadRequestHttpException('Only completed mocks can be edited.');
        }

        if (array_key_exists('instructor_note', $data)) {
            $mock->instructor_note = trim((string) $data['instructor_note']) ?: null;
        }
        if (array_key_exists('learner_summary', $data)) {
            $mock->learner_summary = trim((string) $data['learner_summary']) ?: null;
        }
        if (array_key_exists('private_note', $data)) {
            $mock->private_note = trim((string) $data['private_note']) ?: null;
        }
        if (array_key_exists('suggested_next_focus', $data)) {
            $mock->suggested_next_focus = trim((string) $data['suggested_next_focus']) ?: null;
        }

        $mock->updated_at = gmdate('Y-m-d H:i:s');
        $mock->save(false, ['instructor_note', 'learner_summary', 'private_note', 'suggested_next_focus', 'updated_at']);

        return $this->serialize($mock, detailed: true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFaultNote(int $mockId, int $faultId, array $data): array
    {
        $mock = $this->findMockOrFail($mockId);
        $fault = MockTestFault::findOne([
            'id' => $faultId,
            'mock_test_id' => (int) $mock->id,
            'organisation_id' => (int) $mock->organisation_id,
        ]);
        if ($fault === null || $fault->undone_at !== null) {
            throw new NotFoundHttpException('Fault not found.');
        }

        $fault->note = trim((string) ($data['note'] ?? '')) ?: null;
        $fault->save(false, ['note']);

        return $this->serializeFault($fault);
    }

    /**
     * @return array{items: list<array<string, mixed>>, patterns: array<string, mixed>}
     */
    public function listForLearner(int $learnerId): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $this->assertLearnerInOrg($learnerId, $orgId);

        /** @var MockTest[] $mocks */
        $mocks = MockTest::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerId,
            ])
            ->andWhere(['in', 'status', [MockTest::STATUS_COMPLETED, MockTest::STATUS_ABANDONED]])
            ->orderBy(['started_at' => SORT_DESC])
            ->all();

        $items = array_map(fn (MockTest $m) => $this->serialize($m), $mocks);

        return [
            'items' => $items,
            'patterns' => $this->buildPatterns($mocks),
            'comparison' => count($mocks) >= 2 ? $this->compareMocks($mocks[0], $mocks[1]) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mockEvidenceForSkill(int $learnerId, int $organisationId, int $skillId, int $limit = 10): array
    {
        $sql = <<<'SQL'
SELECT f.*, m.started_at AS mock_started_at, m.id AS mock_id, m.result
FROM mock_test_faults f
INNER JOIN mock_tests m ON m.id = f.mock_test_id
WHERE f.organisation_id = :org
  AND m.learner_id = :learner
  AND f.skill_id = :skill
  AND f.undone_at IS NULL
  AND m.status = :completed
ORDER BY f.recorded_at DESC
LIMIT :lim
SQL;

        $rows = Yii::$app->db->createCommand($sql, [
            ':org' => $organisationId,
            ':learner' => $learnerId,
            ':skill' => $skillId,
            ':completed' => MockTest::STATUS_COMPLETED,
            ':lim' => $limit,
        ])->queryAll();

        return array_map(static fn (array $r) => [
            'mock_test_id' => (int) $r['mock_id'],
            'fault_type' => $r['fault_type'],
            'fault_label' => $r['fault_label'],
            'recorded_at' => $r['recorded_at'],
            'mock_result' => $r['result'],
            'note' => $r['note'],
        ], $rows);
    }

    /**
     * @return list<array{label: string, count: int, reason: string}>
     */
    public function suggestNextFocus(MockTest $mock): array
    {
        /** @var MockTestFault[] $faults */
        $faults = MockTestFault::find()
            ->andWhere(['mock_test_id' => (int) $mock->id, 'undone_at' => null])
            ->all();

        $counts = [];
        foreach ($faults as $fault) {
            $key = $fault->fault_label;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $suggestions = [];
        foreach (array_slice($counts, 0, 5, true) as $label => $count) {
            $suggestions[] = [
                'label' => $label,
                'count' => $count,
                'reason' => 'Appeared ' . $count . ' ' . ($count === 1 ? 'time' : 'times') . ' in this mock',
            ];
        }

        return $suggestions;
    }

    /**
     * Apply suggested next focus to learner record (instructor confirms).
     */
    public function applyNextFocus(int $mockId, string $focus): array
    {
        $mock = $this->findMockOrFail($mockId);
        if ($mock->status !== MockTest::STATUS_COMPLETED) {
            throw new BadRequestHttpException('Mock must be completed first.');
        }
        $focus = trim($focus);
        if ($focus === '') {
            throw new BadRequestHttpException('Next focus is required.');
        }

        $learner = $mock->learner;
        if ($learner === null) {
            throw new NotFoundHttpException('Learner not found.');
        }
        $learner->next_focus = $focus;
        $learner->updated_at = gmdate('Y-m-d H:i:s');
        $learner->save(false, ['next_focus', 'updated_at']);

        $mock->suggested_next_focus = $focus;
        $mock->updated_at = gmdate('Y-m-d H:i:s');
        $mock->save(false, ['suggested_next_focus', 'updated_at']);

        return ['next_focus' => $focus];
    }

    public function findActiveForLesson(int $lessonId): ?MockTest
    {
        $orgId = TenantContext::requireOrganisationId();

        $mock = MockTest::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'lesson_id' => $lessonId,
                'status' => MockTest::STATUS_IN_PROGRESS,
            ])
            ->one();

        return $mock instanceof MockTest ? $mock : null;
    }

    public function completedForLesson(int $lessonId, int $orgId): ?MockTest
    {
        $mock = MockTest::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'lesson_id' => $lessonId,
                'status' => MockTest::STATUS_COMPLETED,
            ])
            ->orderBy(['finished_at' => SORT_DESC])
            ->one();

        return $mock instanceof MockTest ? $mock : null;
    }

    private function findMockOrFail(int $mockId): MockTest
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var MockTest|null $mock */
        $mock = TenantContext::scopeByOrganisation(MockTest::find())
            ->andWhere(['id' => $mockId])
            ->one();
        if ($mock === null) {
            throw new NotFoundHttpException('Mock test not found.');
        }

        return $mock;
    }

    private function assertLearnerInOrg(int $learnerId, int $orgId): void
    {
        $exists = Learner::find()
            ->andWhere(['id' => $learnerId, 'organisation_id' => $orgId])
            ->exists();
        if (!$exists) {
            throw new NotFoundHttpException('Pupil not found.');
        }
    }

    private function assertLearnerCanView(MockTest $mock): void
    {
        $account = \app\components\PortalContext::requireAccount();
        if ((int) $account->learner_id !== (int) $mock->learner_id) {
            throw new ForbiddenHttpException('Not your mock test.');
        }
    }

    private function findAssociableLesson(Learner $learner): ?Lesson
    {
        $now = gmdate('Y-m-d H:i:s');
        $dayStart = gmdate('Y-m-d 00:00:00', strtotime('-12 hours'));
        $dayEnd = gmdate('Y-m-d 23:59:59', strtotime('+12 hours'));

        /** @var Lesson|null $lesson */
        $lesson = Lesson::find()
            ->andWhere([
                'organisation_id' => (int) $learner->organisation_id,
                'learner_id' => (int) $learner->id,
            ])
            ->andWhere(['in', 'status', [Lesson::STATUS_SCHEDULED, Lesson::STATUS_COMPLETED]])
            ->andWhere(['between', 'starts_at', $dayStart, $dayEnd])
            ->orderBy(['starts_at' => SORT_DESC])
            ->one();

        return $lesson;
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

    private function elapsedSeconds(MockTest $mock, string $nowUtc): int
    {
        $start = strtotime($mock->started_at . ' UTC');
        $end = strtotime($nowUtc . ' UTC');
        if ($start === false || $end === false) {
            return 0;
        }

        return max(0, $end - $start);
    }

    private function recountFaults(MockTest $mock): void
    {
        $driving = (int) MockTestFault::find()
            ->andWhere(['mock_test_id' => (int) $mock->id, 'fault_type' => MockTestFault::TYPE_DRIVING, 'undone_at' => null])
            ->count();
        $serious = (int) MockTestFault::find()
            ->andWhere(['mock_test_id' => (int) $mock->id, 'fault_type' => MockTestFault::TYPE_SERIOUS, 'undone_at' => null])
            ->count();
        $dangerous = (int) MockTestFault::find()
            ->andWhere(['mock_test_id' => (int) $mock->id, 'fault_type' => MockTestFault::TYPE_DANGEROUS, 'undone_at' => null])
            ->count();

        $mock->driving_faults_count = $driving;
        $mock->serious_faults_count = $serious;
        $mock->dangerous_faults_count = $dangerous;
        $mock->updated_at = gmdate('Y-m-d H:i:s');
        $mock->save(false, ['driving_faults_count', 'serious_faults_count', 'dangerous_faults_count', 'updated_at']);
    }

    private function buildLearnerSummary(MockTest $mock): string
    {
        $parts = [];
        $parts[] = (int) $mock->driving_faults_count . ' driving fault' . ((int) $mock->driving_faults_count === 1 ? '' : 's');
        if ((int) $mock->serious_faults_count > 0) {
            $parts[] = (int) $mock->serious_faults_count . ' serious';
        }
        if ((int) $mock->dangerous_faults_count > 0) {
            $parts[] = (int) $mock->dangerous_faults_count . ' dangerous';
        }

        return implode(', ', $parts);
    }

    /**
     * @param MockTest[] $completedMocks newest first
     * @return array<string, mixed>
     */
    private function buildPatterns(array $completedMocks): array
    {
        $completed = array_values(array_filter(
            $completedMocks,
            static fn (MockTest $m) => $m->status === MockTest::STATUS_COMPLETED,
        ));
        if ($completed === []) {
            return ['repeated_faults' => [], 'serious_trend' => []];
        }

        $last3 = array_slice($completed, 0, 3);
        $faultCountsByLabel = [];
        foreach ($last3 as $mock) {
            /** @var MockTestFault[] $faults */
            $faults = MockTestFault::find()
                ->andWhere(['mock_test_id' => (int) $mock->id, 'undone_at' => null])
                ->all();
            $byLabel = [];
            foreach ($faults as $f) {
                $byLabel[$f->fault_label] = ($byLabel[$f->fault_label] ?? 0) + 1;
            }
            $faultCountsByLabel[] = [
                'mock_id' => (int) $mock->id,
                'date' => $this->formatDate($mock->started_at),
                'result' => $mock->result,
                'faults_by_label' => $byLabel,
            ];
        }

        $seriousTrend = array_map(static fn (MockTest $m) => [
            'mock_id' => (int) $m->id,
            'date' => gmdate('j M', strtotime($m->started_at . ' UTC')),
            'serious' => (int) $m->serious_faults_count,
            'dangerous' => (int) $m->dangerous_faults_count,
            'driving' => (int) $m->driving_faults_count,
        ], $last3);

        return [
            'last_mocks' => $faultCountsByLabel,
            'serious_trend' => $seriousTrend,
        ];
    }

    private function compareMocks(MockTest $newer, MockTest $older): array
    {
        return [
            'newer' => $this->serialize($newer),
            'older' => $this->serialize($older),
            'driving_faults' => [
                'newer' => (int) $newer->driving_faults_count,
                'older' => (int) $older->driving_faults_count,
            ],
            'serious_faults' => [
                'newer' => (int) $newer->serious_faults_count,
                'older' => (int) $older->serious_faults_count,
            ],
            'dangerous_faults' => [
                'newer' => (int) $newer->dangerous_faults_count,
                'older' => (int) $older->dangerous_faults_count,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MockTest $mock, bool $detailed = false, bool $learnerVisible = false): array
    {
        $learner = $mock->learner;
        $duration = null;
        if ($mock->finished_at !== null) {
            $duration = $this->elapsedSeconds($mock, $mock->finished_at);
        } elseif ($mock->isActive()) {
            $duration = $this->elapsedSeconds($mock, gmdate('Y-m-d H:i:s'));
        }

        $resultLabel = match ($mock->result) {
            MockTest::RESULT_PASS => 'Pass standard',
            MockTest::RESULT_NOT_PASS => 'Not at pass standard',
            default => null,
        };

        $payload = [
            'id' => (int) $mock->id,
            'learner_id' => (int) $mock->learner_id,
            'learner_first_name' => $learner?->first_name,
            'learner_name' => $learner?->fullName,
            'lesson_id' => $mock->lesson_id !== null ? (int) $mock->lesson_id : null,
            'status' => $mock->status,
            'result' => $mock->result,
            'result_label' => $resultLabel,
            'started_at' => $mock->started_at,
            'finished_at' => $mock->finished_at,
            'elapsed_seconds' => $duration,
            'elapsed_display' => $duration !== null ? $this->formatDuration($duration) : null,
            'driving_faults_count' => (int) $mock->driving_faults_count,
            'serious_faults_count' => (int) $mock->serious_faults_count,
            'dangerous_faults_count' => (int) $mock->dangerous_faults_count,
            'learner_summary' => $mock->learner_summary,
            'instructor_note' => $learnerVisible ? $mock->instructor_note : $mock->instructor_note,
            'suggested_next_focus' => $mock->suggested_next_focus,
            'date_display' => $this->formatDate($mock->started_at),
        ];

        if (!$learnerVisible) {
            $payload['private_note'] = $mock->private_note;
        }

        if ($detailed) {
            /** @var MockTestFault[] $faults */
            $faults = MockTestFault::find()
                ->andWhere(['mock_test_id' => (int) $mock->id, 'undone_at' => null])
                ->with('skill')
                ->orderBy(['recorded_at' => SORT_ASC])
                ->all();
            $payload['faults'] = array_map(fn ($f) => $this->serializeFault($f), $faults);
            $payload['fault_summary'] = $this->groupFaults($faults);
            $payload['recent_faults'] = array_slice(array_reverse($payload['faults']), 0, 5);
        }

        return $payload;
    }

    /**
     * @param MockTestFault[] $faults
     * @return list<array<string, mixed>>
     */
    private function groupFaults(array $faults): array
    {
        $groups = [];
        foreach ($faults as $fault) {
            $area = explode(' · ', $fault->fault_label)[0] ?? $fault->fault_label;
            if (!isset($groups[$area])) {
                $groups[$area] = [
                    'area' => $area,
                    'items' => [],
                ];
            }
            $key = $fault->fault_label;
            if (!isset($groups[$area]['items'][$key])) {
                $groups[$area]['items'][$key] = [
                    'label' => $fault->fault_label,
                    'skill_code' => $fault->skill?->code,
                    'driving' => 0,
                    'serious' => 0,
                    'dangerous' => 0,
                    'fault_ids' => [],
                ];
            }
            $groups[$area]['items'][$key][$fault->fault_type]++;
            $groups[$area]['items'][$key]['fault_ids'][] = (int) $fault->id;
        }

        $out = [];
        foreach ($groups as $group) {
            $group['items'] = array_values($group['items']);
            $out[] = $group;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFault(MockTestFault $fault): array
    {
        return [
            'id' => (int) $fault->id,
            'fault_type' => $fault->fault_type,
            'fault_type_label' => match ($fault->fault_type) {
                MockTestFault::TYPE_DRIVING => 'Driving fault',
                MockTestFault::TYPE_SERIOUS => 'Serious fault',
                MockTestFault::TYPE_DANGEROUS => 'Dangerous fault',
                default => $fault->fault_type,
            },
            'fault_code' => $fault->fault_code,
            'fault_label' => $fault->fault_label,
            'skill_id' => (int) $fault->skill_id,
            'skill_code' => $fault->skill?->code,
            'note' => $fault->note,
            'recorded_at' => $fault->recorded_at,
            'time_display' => gmdate('H:i', strtotime($fault->recorded_at . ' UTC')),
            'elapsed_seconds' => $fault->elapsed_seconds,
            'route_moment_id' => $fault->route_moment_id,
        ];
    }

    private function formatDuration(int $seconds): string
    {
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;

        return sprintf('%d:%02d', $m, $s);
    }

    private function formatDate(string $utc): string
    {
        $ts = strtotime($utc . ' UTC');

        return $ts !== false ? gmdate('j M Y', $ts) : $utc;
    }
}
