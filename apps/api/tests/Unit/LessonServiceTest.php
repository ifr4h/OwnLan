<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Lesson;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class LessonServiceTest extends Unit
{
    protected UnitTester $tester;

    private LessonService $lessons;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();
    }

    public function testCreateDefaultsPickupAndDurationThenCancelKeepsRow(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'lessons@example.com',
            'password' => 'password123',
        ]);

        $pupil = $this->learners->create([
            'first_name' => 'Mia',
            'last_name' => 'Patel',
            'mobile' => '07700900123',
            'default_pickup_address' => '12 High Street',
        ]);

        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-15 10:00',
        ]);

        $this->assertSame(Lesson::STATUS_SCHEDULED, $lesson['status']);
        $this->assertSame(60, $lesson['duration_minutes']);
        $this->assertSame('12 High Street', $lesson['pickup_address']);
        $this->assertSame('Europe/London', $lesson['timezone']);
        $this->assertSame('2026-09-15T10:00', $lesson['starts_at_local']);
        // BST in September: 10:00 London = 09:00 UTC
        $this->assertStringContainsString('2026-09-15T09:00:00', $lesson['starts_at']);

        $cancelled = $this->lessons->cancel((int) $lesson['id']);
        $this->assertSame(Lesson::STATUS_CANCELLED, $cancelled['status']);
        $this->assertNotNull($cancelled['cancelled_at']);
        $this->assertSame(1, (int) Lesson::find()->count());
        $this->assertCount(0, $this->lessons->listUpcoming());
        $this->assertCount(1, $this->lessons->listForLearner((int) $pupil['id']));
    }

    public function testTenantIsolation(): void
    {
        $auth = new AuthService();
        $auth->register([
            'name' => 'Instructor A',
            'email' => 'la@example.com',
            'password' => 'password123',
        ]);
        $pupilA = $this->learners->create([
            'first_name' => 'Asha',
            'last_name' => 'Green',
            'mobile' => '07700900111',
        ]);
        $lessonA = $this->lessons->create([
            'learner_id' => $pupilA['id'],
            'starts_at_local' => '2026-10-01 09:00',
            'duration_minutes' => 90,
        ]);
        $auth->logout();

        $auth->register([
            'name' => 'Instructor B',
            'email' => 'lb@example.com',
            'password' => 'password123',
        ]);
        $this->assertCount(0, $this->lessons->listUpcoming());

        $this->expectException(\yii\web\NotFoundHttpException::class);
        $this->lessons->get((int) $lessonA['id']);
    }
}
