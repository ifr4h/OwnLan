<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\LessonService;
use app\services\SettingsService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\UnauthorizedHttpException;

class SettingsServiceTest extends Unit
{
    protected UnitTester $tester;

    private SettingsService $settings;
    private AuthService $auth;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, learner_portal_accounts, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        TenantContext::clear();
        $this->settings = new SettingsService();
        $this->auth = new AuthService();
    }

    public function testRegisterDefaultsAndUpdateFeedsBookingDuration(): void
    {
        $this->auth->register([
            'name' => 'Alex Instructor',
            'email' => 'settings@example.com',
            'password' => 'password123',
        ]);

        $payload = $this->settings->get();
        $this->assertSame('Alex Instructor', $payload['display_name']);
        $this->assertSame('Europe/London', $payload['timezone']);
        $this->assertSame(60, $payload['default_lesson_duration_minutes']);
        $this->assertSame(3500, $payload['default_hourly_rate_pence']);
        $this->assertSame([1, 2, 3, 4, 5, 6], $payload['work_days']);
        $this->assertSame('09:00', $payload['work_start_time']);
        $this->assertSame('18:00', $payload['work_end_time']);
        $this->assertSame(1, $payload['week_starts_on']);
        $this->assertSame('settings@example.com', $payload['contact_email']);

        $updated = $this->settings->update([
            'display_name' => 'Alex ADI',
            'business_name' => 'Alex Driving',
            'contact_phone' => '07700900111',
            'default_lesson_duration_minutes' => 90,
            'default_hourly_rate' => '40',
            'service_area' => 'SW9, Brixton',
            'cancellation_policy' => '24 hours notice please.',
            'work_days' => [1, 2, 3, 4, 5],
            'work_start_time' => '08:00',
            'work_end_time' => '16:00',
            'week_starts_on' => 7,
            'timezone' => 'Europe/London',
        ]);

        $this->assertSame('Alex ADI', $updated['display_name']);
        $this->assertSame('Alex Driving', $updated['business_name']);
        $this->assertSame(90, $updated['default_lesson_duration_minutes']);
        $this->assertSame(4000, $updated['default_hourly_rate_pence']);
        $this->assertSame([1, 2, 3, 4, 5], $updated['work_days']);
        $this->assertSame(7, $updated['week_starts_on']);
        $this->assertSame('SW9, Brixton', $updated['service_area']);

        $me = $this->auth->currentUserPayload();
        $this->assertSame(90, $me['organisation']['default_lesson_duration_minutes']);
        $this->assertSame(7, $me['organisation']['week_starts_on']);
        $this->assertSame('Alex Driving', $me['organisation']['name']);
        $this->assertSame('Alex ADI', $me['instructor']['display_name']);

        // Create a pupil + lesson with no duration → org default 90
        $learner = (new \app\services\LearnerService())->create([
            'first_name' => 'Sam',
            'last_name' => 'Learner',
            'mobile' => '07700900222',
        ]);
        $lesson = (new LessonService())->create([
            'learner_id' => $learner['id'],
            'starts_at_local' => '2026-09-21 10:00',
        ]);
        $this->assertSame(90, $lesson['duration_minutes']);

        // Soft warning when outside working hours (Sunday + late)
        $check = (new LessonService())->travelCheck([
            'learner_id' => $learner['id'],
            'starts_at_local' => '2026-09-20 19:00', // Sunday
            'duration_minutes' => 60,
        ]);
        $codes = array_column($check['warnings'], 'code');
        $this->assertContains('outside_working_hours', $codes);
    }

    public function testTenantIsolationOnSettings(): void
    {
        $this->auth->register([
            'name' => 'Alpha',
            'email' => 'alpha-settings@example.com',
            'password' => 'password123',
        ]);
        $this->settings->update([
            'business_name' => 'Alpha School',
            'cancellation_policy' => 'Alpha only',
        ]);
        Yii::$app->user->logout();
        TenantContext::clear();

        $this->auth->register([
            'name' => 'Beta',
            'email' => 'beta-settings@example.com',
            'password' => 'password123',
        ]);
        $beta = $this->settings->get();
        $this->assertSame("Beta's driving school", $beta['business_name']);
        $this->assertNull($beta['cancellation_policy']);

        $orgs = Organisation::find()->orderBy(['id' => SORT_ASC])->all();
        $this->assertCount(2, $orgs);
        $this->assertSame('Alpha School', $orgs[0]->name);
        $this->assertSame('Alpha only', $orgs[0]->cancellation_policy);
        $this->assertSame("Beta's driving school", $orgs[1]->name);
    }

    public function testGuestCannotReadSettings(): void
    {
        $this->expectException(UnauthorizedHttpException::class);
        $this->settings->get();
    }
}
