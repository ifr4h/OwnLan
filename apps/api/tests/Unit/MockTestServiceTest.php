<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\MockTestResultRules;
use app\models\MockTest;
use app\models\MockTestFault;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\MockTestService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class MockTestServiceTest extends Unit
{
    protected UnitTester $tester;

    private MockTestService $mocks;
    private LearnerService $learners;
    private LessonService $lessons;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE mock_test_faults, mock_tests, learner_skill_self_assessments, lesson_skills, learner_skill_progress, lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        Yii::$app->cache->flush();

        $this->mocks = new MockTestService();
        $this->learners = new LearnerService();
        $this->lessons = new LessonService();

        (new AuthService())->register([
            'name' => 'Mock Instructor',
            'email' => 'mock-instructor@example.com',
            'password' => 'password123',
        ]);
    }

    public function testStartRecordFinishMockOnLesson(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700901001',
            'email' => 'sarah@example.com',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-09-02 10:00',
            'duration_minutes' => 120,
        ]);

        $mock = $this->mocks->startForLesson((int) $lesson['id']);
        $this->assertSame('in_progress', $mock['status']);
        $this->assertSame('Sarah', $mock['learner_first_name']);

        $fault = $this->mocks->recordFault((int) $mock['id'], [
            'fault_type' => MockTestFault::TYPE_DRIVING,
            'fault_code' => 'junction_observation',
            'client_op_id' => 'op-1',
        ]);
        $this->assertSame('Junctions · Observation', $fault['fault_label']);

        $this->mocks->recordFault((int) $mock['id'], [
            'fault_type' => MockTestFault::TYPE_DRIVING,
            'fault_code' => 'junction_observation',
            'client_op_id' => 'op-2',
        ]);

        $finished = $this->mocks->finish((int) $mock['id'], []);
        $this->assertSame('completed', $finished['status']);
        $this->assertSame(2, $finished['driving_faults_count']);
        $this->assertSame(MockTest::RESULT_PASS, $finished['result']);
        $this->assertSame((int) $lesson['id'], $finished['lesson_id']);
    }

    public function testSeriousFaultCausesNotPassStandard(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Test',
            'mobile' => '07700901002',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-09-03 10:00',
        ]);
        $mock = $this->mocks->startForLesson((int) $lesson['id']);
        $this->mocks->recordFault((int) $mock['id'], [
            'fault_type' => MockTestFault::TYPE_SERIOUS,
            'fault_code' => 'mirrors_before_change',
            'client_op_id' => 'op-s1',
        ]);
        $finished = $this->mocks->finish((int) $mock['id'], []);
        $this->assertSame(MockTest::RESULT_NOT_PASS, $finished['result']);
    }

    public function testUndoFault(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Undo',
            'last_name' => 'Test',
            'mobile' => '07700901003',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-09-04 10:00',
        ]);
        $mock = $this->mocks->startForLesson((int) $lesson['id']);
        $fault = $this->mocks->recordFault((int) $mock['id'], [
            'fault_type' => MockTestFault::TYPE_DRIVING,
            'fault_code' => 'signals_timing',
            'client_op_id' => 'op-u1',
        ]);
        $this->mocks->undoFault((int) $mock['id'], (int) $fault['id']);
        $view = $this->mocks->view((int) $mock['id']);
        $this->assertSame(0, $view['driving_faults_count']);
    }

    public function testClientOpIdempotency(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Idem',
            'last_name' => 'Potent',
            'mobile' => '07700901004',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-09-05 10:00',
        ]);
        $mock = $this->mocks->startForLesson((int) $lesson['id'], 'session-abc');
        $this->mocks->recordFault((int) $mock['id'], [
            'fault_type' => MockTestFault::TYPE_DRIVING,
            'fault_code' => 'speed_appropriate',
            'client_op_id' => 'same-op',
        ]);
        $this->mocks->recordFault((int) $mock['id'], [
            'fault_type' => MockTestFault::TYPE_DRIVING,
            'fault_code' => 'speed_appropriate',
            'client_op_id' => 'same-op',
        ]);
        $view = $this->mocks->view((int) $mock['id']);
        $this->assertSame(1, $view['driving_faults_count']);
    }

    public function testAbandonedMockHasNoResult(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Abandon',
            'last_name' => 'Test',
            'mobile' => '07700901005',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-09-06 10:00',
        ]);
        $mock = $this->mocks->startForLesson((int) $lesson['id']);
        $abandoned = $this->mocks->abandon((int) $mock['id']);
        $this->assertSame('abandoned', $abandoned['status']);
        $this->assertNull($abandoned['result']);
    }

    public function testResultRules(): void
    {
        $pass = MockTestResultRules::calculate(15, 0, 0);
        $this->assertSame(MockTest::RESULT_PASS, $pass['result']);

        $failDriving = MockTestResultRules::calculate(16, 0, 0);
        $this->assertSame(MockTest::RESULT_NOT_PASS, $failDriving['result']);

        $failSerious = MockTestResultRules::calculate(0, 1, 0);
        $this->assertSame(MockTest::RESULT_NOT_PASS, $failSerious['result']);
    }

    public function testCannotRecordOnCompletedMock(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Done',
            'last_name' => 'Mock',
            'mobile' => '07700901006',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-09-07 10:00',
        ]);
        $mock = $this->mocks->startForLesson((int) $lesson['id']);
        $this->mocks->finish((int) $mock['id'], []);

        $this->expectException(BadRequestHttpException::class);
        $this->mocks->recordFault((int) $mock['id'], [
            'fault_type' => MockTestFault::TYPE_DRIVING,
            'fault_code' => 'gears',
            'client_op_id' => 'late-op',
        ]);
    }

    public function testTenantIsolationOnView(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Iso',
            'last_name' => 'One',
            'mobile' => '07700901007',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-09-08 10:00',
        ]);
        $mock = $this->mocks->startForLesson((int) $lesson['id']);
        $mockId = (int) $mock['id'];

        Yii::$app->user->logout();
        (new AuthService())->register([
            'name' => 'Other Org',
            'email' => 'other@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(NotFoundHttpException::class);
        $this->mocks->view($mockId);
    }
}
