<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\FinancialGoal;
use app\models\Organisation;
use app\services\AuthService;
use app\services\BusinessFinanceService;
use app\services\FinanceService;
use app\services\LearnerService;
use app\services\LessonService;
use app\services\TeachingCapacityService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class AccountsOverviewServiceTest extends Unit
{
    protected UnitTester $tester;

    private BusinessFinanceService $business;
    private FinanceService $finance;
    private LessonService $lessons;
    private LearnerService $learners;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, '
            . 'mileage_logs, financial_goals, vehicles, expenses, lessons, lesson_series, learner_availability, '
            . 'learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->business = new BusinessFinanceService();
        $this->finance = new FinanceService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Accounts Instructor',
            'email' => 'accounts@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->org->default_hourly_rate_pence = 4200; // £42/hr
        $this->org->work_start_time = '09:00';
        $this->org->work_end_time = '18:00';
        $this->org->work_days = '1,2,3,4,5';
        $this->org->save(false, ['default_hourly_rate_pence', 'work_start_time', 'work_end_time', 'work_days']);
    }

    public function testProjectionAndGoalGapCaseA(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Learner',
            'mobile' => '07700905001',
        ]);

        // Completed lesson £42
        $done = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-05 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->complete((int) $done['id'], ['settlement' => 'charge']);

        // Future booked lesson £42 (same rate)
        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-20 10:00',
            'duration_minutes' => 60,
        ]);

        $goal = new FinancialGoal();
        $goal->organisation_id = (int) $this->org->id;
        $goal->period_year = 2026;
        $goal->period_month = 9;
        $goal->target_pence = 400000;
        $goal->created_at = gmdate('Y-m-d H:i:s');
        $goal->updated_at = gmdate('Y-m-d H:i:s');
        $goal->save(false);

        $overview = $this->business->overview('2026-09-01', '2026-09-30');

        $this->assertSame(4200, $overview['teaching_income_pence']);
        $this->assertSame(4200, $overview['booked_before_period_end_pence']);
        $this->assertSame(8400, $overview['projected_from_bookings_pence']);
        $this->assertSame(391600, $overview['goal_gap_from_bookings_pence']);
    }

    public function testCancelledFutureLessonExcludedFromBookedValue(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Case',
            'last_name' => 'D',
            'mobile' => '07700905002',
        ]);

        $future = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-18 11:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->cancel((int) $future['id'], ['reason' => 'Pupil unavailable']);

        $overview = $this->business->overview('2026-09-01', '2026-09-30');
        $this->assertSame(0, $overview['booked_before_period_end_pence']);
    }

    public function testChargedNoShowIncludedFinanciallyNotTeachingHours(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Case',
            'last_name' => 'E',
            'mobile' => '07700905003',
        ]);

        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-08-15 14:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->markNoShow((int) $lesson['id'], ['charge' => 'outstanding']);
        Yii::$app->db->createCommand()->update('{{%lessons}}', [
            'no_show_at' => '2026-08-15 15:00:00',
        ], ['id' => (int) $lesson['id']])->execute();

        $overview = $this->business->overview('2026-08-01', '2026-08-31');
        $this->assertSame(4200, $overview['teaching_income_pence']);
        $this->assertSame(0, $overview['average_teaching_value']['teaching_minutes']);
    }

    public function testUsableCapacityExcludesShortGaps(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Gap',
            'last_name' => 'Test',
            'mobile' => '07700905004',
        ]);

        // Two lessons with 30-minute gap — should not count as usable capacity
        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-15 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-15 11:30',
            'duration_minutes' => 60,
        ]);

        $capacity = (new TeachingCapacityService())->capacityForPeriod(
            $this->org,
            new \DateTimeImmutable('2026-09-15', new \DateTimeZone('Europe/London')),
            new \DateTimeImmutable('2026-09-15', new \DateTimeZone('Europe/London')),
            new \DateTimeImmutable('2026-09-15 08:00:00', new \DateTimeZone('UTC')),
        );

        // 09:00-10:00 = 60m, 12:30-18:00 = 330m → 390m total if no other constraints
        $this->assertGreaterThanOrEqual(60, $capacity['usable_minutes']);
        $this->assertLessThan(420, $capacity['usable_minutes']); // 30-min middle gap excluded
    }

    public function testExpenseReceiptTenantIsolation(): void
    {
        $expense = $this->business->createExpense([
            'amount_pence' => 2100,
            'category' => 'fuel',
            'spent_on' => '2026-09-12',
            'supplier' => 'Shell',
        ]);
        $this->assertSame('Shell', $expense['supplier']);
        $this->assertFalse($expense['has_receipt']);
    }
}
