<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\services\AuthService;
use app\services\AvailabilityService;
use app\services\EmptySeatService;
use app\services\GapMatchingService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\FakeTravelProvider;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;
use Yii;

class EmptySeatServiceTest extends Unit
{
    protected UnitTester $tester;

    private LearnerService $learners;
    private LessonService $lessons;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->learners = new LearnerService();
        $this->lessons = new LessonService();
    }

    public function testCancelReturnsEmptySeatWithBestMatch(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'seat@example.com',
            'password' => 'password123',
        ]);

        $prev = $this->learners->create([
            'first_name' => 'Prev',
            'last_name' => 'One',
            'mobile' => '07700901001',
            'default_pickup_address' => 'Prev LS1 1AA',
        ]);
        $sarah = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Smith',
            'mobile' => '07700901002',
            'default_pickup_address' => 'Sarah LS1 2BB',
        ]);
        $yusuf = $this->learners->create([
            'first_name' => 'Yusuf',
            'last_name' => 'Khan',
            'mobile' => '07700901003',
            'default_pickup_address' => 'Yusuf LS1 1AA',
        ]);

        $this->seedWeeklyHistory((int) $yusuf['id'], 120);
        (new AvailabilityService())->replaceForLearner((int) $yusuf['id'], [
            ['weekday' => 5, 'mode' => 'flexible'],
        ]);

        $this->lessons->create([
            'learner_id' => $prev['id'],
            'starts_at_local' => '2026-09-18 11:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Prev LS1 1AA',
        ]);
        $sarahLesson = $this->lessons->create([
            'learner_id' => $sarah['id'],
            'starts_at_local' => '2026-09-18 13:00',
            'duration_minutes' => 120,
            'pickup_address' => 'Sarah LS1 2BB',
        ]);

        $fake = new FakeTravelProvider([
            'prev ls1 1aa|yusuf ls1 1aa' => 6,
        ]);
        $seat = new EmptySeatService($fake, new GapMatchingService($fake));

        // Cancel via service under test path with injected matcher by cancelling then calling seat
        $cancelled = $this->lessons->cancel((int) $sarahLesson['id']);
        $this->assertArrayHasKey('empty_seat', $cancelled);

        // Re-run with fake travel for deterministic reasons (default heuristic may differ)
        $org = \app\models\Organisation::find()->one();
        $lesson = \app\models\Lesson::findOne(['id' => $sarahLesson['id']]);
        $this->assertNotNull($org);
        $this->assertNotNull($lesson);

        $recovery = $seat->forCancelledLesson(
            $lesson,
            $org,
            new DateTimeImmutable('2026-09-17 10:00:00', new DateTimeZone('UTC')),
        );

        $this->assertTrue($recovery['applicable']);
        $this->assertSame('Sarah Smith cancelled', $recovery['headline']);
        $this->assertStringContainsString('13:00–15:00', $recovery['slot_label']);
        $this->assertGreaterThanOrEqual(1, $recovery['match_count']);
        $this->assertSame('Yusuf Khan', $recovery['best_match']['learner_name']);
        $this->assertContains('Available', $recovery['best_match']['reasons']);
        $this->assertContains('6 min from previous lesson', $recovery['best_match']['reasons']);
        $this->assertContains('Due another lesson', $recovery['best_match']['reasons']);
        $this->assertContains('Normally books 2 hours', $recovery['best_match']['reasons']);
    }

    public function testPastSlotNotApplicable(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'seat2@example.com',
            'password' => 'password123',
        ]);
        $pupil = $this->learners->create([
            'first_name' => 'Old',
            'last_name' => 'Slot',
            'mobile' => '07700901011',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-08-01 10:00',
            'duration_minutes' => 60,
        ]);
        $cancelled = $this->lessons->cancel((int) $lesson['id']);
        $this->assertFalse($cancelled['empty_seat']['applicable']);
    }

    /**
     * Seed enough completed 2-hour lessons for history mode + due pattern.
     */
    private function seedWeeklyHistory(int $learnerId, int $duration): void
    {
        for ($i = 0; $i < 5; $i++) {
            $day = (new DateTimeImmutable('2026-08-07'))->modify('+' . ($i * 7) . ' days')->format('Y-m-d');
            $lesson = $this->lessons->create([
                'learner_id' => $learnerId,
                'starts_at_local' => $day . ' 10:00',
                'duration_minutes' => $duration,
            ]);
            Yii::$app->db->createCommand()->update('lessons', [
                'status' => 'completed',
                'completed_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $lesson['id']])->execute();
        }
    }
}
