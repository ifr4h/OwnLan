<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\models\PracticalTest;
use app\models\PracticalTestFault;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\PracticalTestService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class PracticalTestServiceTest extends Unit
{
    protected UnitTester $tester;

    private PracticalTestService $tests;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE practical_test_faults, practical_tests, mock_test_faults, mock_tests, learner_skill_self_assessments, lesson_skills, learner_skill_progress, lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        Yii::$app->cache->flush();

        $this->tests = new PracticalTestService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Test Trends Instructor',
            'email' => 'test-trends@example.com',
            'password' => 'password123',
        ]);
    }

    public function testCreateAndStatsRollingWindow(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Amira',
            'last_name' => 'Khan',
            'mobile' => '07700902001',
            'email' => 'amira@example.com',
            'test_centre' => 'Croydon',
        ]);

        $created = $this->tests->create([
            'learner_id' => $pupil['id'],
            'test_date' => '2026-03-10',
            'result' => PracticalTest::RESULT_FAIL,
            'accompanied' => true,
            'examiner_action' => false,
            'faults' => [
                ['fault_code' => 'junction_observation', 'fault_type' => PracticalTestFault::TYPE_DRIVING, 'count' => 3],
                ['fault_code' => 'mirrors_before_change', 'fault_type' => PracticalTestFault::TYPE_SERIOUS, 'count' => 1],
            ],
        ]);

        $this->assertSame('fail', $created['result']);
        $this->assertSame(3, $created['driving_faults_count']);
        $this->assertSame(1, $created['serious_faults_count']);
        $this->assertCount(2, $created['faults']);

        $this->tests->create([
            'learner_id' => $pupil['id'],
            'test_date' => '2026-06-01',
            'result' => PracticalTest::RESULT_PASS,
            'accompanied' => true,
            'examiner_action' => false,
            'mark_learner_passed' => true,
            'faults' => [
                ['fault_code' => 'junction_observation', 'fault_type' => PracticalTestFault::TYPE_DRIVING, 'count' => 2],
                ['fault_code' => 'control_steering', 'fault_type' => PracticalTestFault::TYPE_DRIVING, 'count' => 1],
            ],
        ]);

        $stats = $this->tests->stats('2026-01-01', '2026-12-31');
        $this->assertSame(2, $stats['summary']['tests_taken']);
        $this->assertSame(1, $stats['summary']['tests_passed']);
        $this->assertSame(50.0, $stats['summary']['pass_rate_percent']);
        $this->assertGreaterThanOrEqual(1, count($stats['faults_by_category']));
        $this->assertSame('junction_observation', $stats['faults_by_category'][0]['fault_code']);
        $this->assertSame(5, $stats['faults_by_category'][0]['driving']);

        $learner = \app\models\Learner::findOne((int) $pupil['id']);
        $this->assertNotNull($learner);
        $this->assertSame('passed', $learner->lifecycle);
    }

    public function testUpdateReplacesFaults(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Cole',
            'mobile' => '07700902002',
        ]);
        $created = $this->tests->create([
            'learner_id' => $pupil['id'],
            'test_date' => '2026-04-01',
            'result' => PracticalTest::RESULT_FAIL,
            'faults' => [
                ['fault_code' => 'speed_limits', 'fault_type' => PracticalTestFault::TYPE_DRIVING, 'count' => 4],
            ],
        ]);

        $updated = $this->tests->update((int) $created['id'], [
            'result' => PracticalTest::RESULT_FAIL,
            'test_date' => '2026-04-01',
            'faults' => [
                ['fault_code' => 'speed_limits', 'fault_type' => PracticalTestFault::TYPE_DANGEROUS, 'count' => 1],
            ],
        ]);

        $this->assertSame(0, $updated['driving_faults_count']);
        $this->assertSame(1, $updated['dangerous_faults_count']);
        $this->assertSame('dangerous', $updated['faults'][0]['fault_type']);
    }
}
