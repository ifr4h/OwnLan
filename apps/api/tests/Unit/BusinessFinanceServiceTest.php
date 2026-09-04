<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\BusinessFinanceService;
use app\services\FinanceService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class BusinessFinanceServiceTest extends Unit
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
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->business = new BusinessFinanceService();
        $this->finance = new FinanceService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Biz Instructor',
            'email' => 'biz@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->org->default_hourly_rate_pence = 3500;
        $this->org->save(false, ['default_hourly_rate_pence']);
    }

    public function testOverviewUsesLivePaymentsChargesAndExpenses(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Ada',
            'last_name' => 'Owes',
            'mobile' => '07700904001',
        ]);

        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-10 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->complete((int) $lesson['id'], ['settlement' => 'charge']);

        $this->finance->recordPayment((int) $pupil['id'], [
            'amount_pence' => 2000,
            'method' => 'cash',
            'recorded_at_local' => '2026-09-10 11:00',
        ]);

        $this->business->createExpense([
            'amount_pence' => 4500,
            'category' => 'fuel',
            'spent_on' => '2026-09-12',
            'notes' => 'Fill-up',
        ]);

        $overview = $this->business->overview('2026-09-01', '2026-09-30');

        $this->assertSame(2000, $overview['money_received_pence']);
        $this->assertSame('£20.00', $overview['money_received_label']);
        // £35 charge − £20 paid = £15 still owed
        $this->assertSame(1500, $overview['still_owed_pence']);
        $this->assertSame(4500, $overview['spending_pence']);
        $this->assertSame(-2500, $overview['left_after_spending_pence']);
        $this->assertSame('−£25.00', $overview['left_after_spending_label']);

        $this->assertCount(1, $overview['who_owes']);
        $this->assertSame('Ada Owes', $overview['who_owes'][0]['learner_name']);
        $this->assertSame(1500, $overview['who_owes'][0]['amount_owed_pence']);

        $this->assertSame('Fuel', $overview['spending_by_category'][0]['label']);
        $this->assertNotEmpty($overview['categories']);
        $this->assertNotEmpty($overview['presets']);
    }

    public function testCsvExportIncludesIncomeSpendingAndOwed(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Bea',
            'last_name' => 'Csv',
            'mobile' => '07700904002',
        ]);
        $this->finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 2,
            'price_pence' => 7000,
            'record_payment' => true,
            'purchased_at_local' => '2026-09-05 09:00',
        ]);
        $this->business->createExpense([
            'amount_pence' => 1200,
            'category' => 'phone',
            'spent_on' => '2026-09-06',
        ]);

        $csv = $this->business->exportCsv('2026-09-01', '2026-09-30');
        $this->assertStringContainsString('money_received', $csv);
        $this->assertStringContainsString('spending', $csv);
        $this->assertStringContainsString('Phone', $csv);
        $this->assertStringContainsString('7000', $csv);
        $this->assertStringContainsString('1200', $csv);
    }

    public function testVoidExpenseDropsFromTotals(): void
    {
        $expense = $this->business->createExpense([
            'amount_pence' => 3000,
            'category' => 'insurance',
            'spent_on' => '2026-09-01',
        ]);
        $overview = $this->business->overview('2026-09-01', '2026-09-30');
        $this->assertSame(3000, $overview['spending_pence']);

        $this->business->voidExpense((int) $expense['id'], ['reason' => 'Entered twice']);
        $after = $this->business->overview('2026-09-01', '2026-09-30');
        $this->assertSame(0, $after['spending_pence']);
        $this->assertSame(0, $after['expense_count']);
    }

    public function testRejectsFloatExpenseAmount(): void
    {
        $this->expectException(\yii\web\BadRequestHttpException::class);
        $this->business->createExpense([
            'amount_pence' => 12.5,
            'category' => 'other',
            'spent_on' => '2026-09-01',
        ]);
    }
}
