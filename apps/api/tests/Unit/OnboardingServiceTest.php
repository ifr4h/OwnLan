<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class OnboardingServiceTest extends Unit
{
    protected UnitTester $tester;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, learner_portal_accounts, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
    }

    public function testStagesProgressWithPupilsAndLessons(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'onboard@example.com',
            'password' => 'password123',
        ]);

        $me = (new AuthService())->currentUserPayload();
        $this->assertSame('add_pupils', $me['onboarding']['stage']);
        $this->assertTrue($me['onboarding']['suggest_business_confirm']);
        $this->assertSame(0, $me['onboarding']['pupil_count']);

        $pupil = (new LearnerService())->create([
            'first_name' => 'Sam',
            'last_name' => 'Lee',
            'mobile' => '07700900111',
        ]);
        $me = (new AuthService())->currentUserPayload();
        $this->assertSame('book_lesson', $me['onboarding']['stage']);
        $this->assertSame(1, $me['onboarding']['pupil_count']);

        (new LessonService())->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-21 10:00',
            'duration_minutes' => 60,
        ]);
        $me = (new AuthService())->currentUserPayload();
        $this->assertContains($me['onboarding']['stage'], ['use_today', 'done']);
        $this->assertSame(1, $me['onboarding']['lesson_count']);
        $this->assertFalse($me['onboarding']['suggest_business_confirm']);
    }

    public function testStatusIsOrganisationScoped(): void
    {
        (new AuthService())->register([
            'name' => 'Alpha',
            'email' => 'onboard-a@example.com',
            'password' => 'password123',
        ]);
        (new LearnerService())->create([
            'first_name' => 'A',
            'last_name' => 'One',
            'mobile' => '07700900222',
        ]);
        Yii::$app->user->logout();
        TenantContext::clear();

        (new AuthService())->register([
            'name' => 'Beta',
            'email' => 'onboard-b@example.com',
            'password' => 'password123',
        ]);
        $me = (new AuthService())->currentUserPayload();
        $this->assertSame('add_pupils', $me['onboarding']['stage']);
        $this->assertSame(0, $me['onboarding']['pupil_count']);
    }
}
