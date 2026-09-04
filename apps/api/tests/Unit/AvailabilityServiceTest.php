<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\LearnerAvailability;
use app\services\AuthService;
use app\services\AvailabilityService;
use app\services\LearnerService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;

class AvailabilityServiceTest extends Unit
{
    protected UnitTester $tester;

    private AvailabilityService $availability;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->availability = new AvailabilityService();
        $this->learners = new LearnerService();
    }

    public function testReplaceAndListOptionalWindows(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'avail@example.com',
            'password' => 'password123',
        ]);

        $pupil = $this->learners->create([
            'first_name' => 'Mia',
            'last_name' => 'Patel',
            'mobile' => '07700900123',
        ]);

        $this->assertSame([], $this->availability->listForLearner((int) $pupil['id']));

        $saved = $this->availability->replaceForLearner((int) $pupil['id'], [
            ['weekday' => 1, 'mode' => 'after', 'start_time' => '16:00'],
            ['weekday' => 3, 'mode' => 'between', 'start_time' => '09:00', 'end_time' => '14:00'],
            ['weekday' => 6, 'mode' => 'flexible'],
        ]);

        $this->assertCount(3, $saved);
        $this->assertSame('After 16:00', $saved[0]['label']);
        $this->assertSame('09:00–14:00', $saved[1]['label']);
        $this->assertSame('Flexible', $saved[2]['label']);
        $this->assertSame(3, (int) LearnerAvailability::find()->count());

        $cleared = $this->availability->replaceForLearner((int) $pupil['id'], []);
        $this->assertSame([], $cleared);
        $this->assertSame(0, (int) LearnerAvailability::find()->count());
    }

    public function testInvalidBetweenRejected(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'avail2@example.com',
            'password' => 'password123',
        ]);
        $pupil = $this->learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Lee',
            'mobile' => '07700900444',
        ]);

        $this->expectException(BadRequestHttpException::class);
        $this->availability->replaceForLearner((int) $pupil['id'], [
            ['weekday' => 2, 'mode' => 'between', 'start_time' => '14:00', 'end_time' => '09:00'],
        ]);
    }
}
