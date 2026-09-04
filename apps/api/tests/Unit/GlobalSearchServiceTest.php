<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\BusinessFinanceService;
use app\services\FinanceService;
use app\services\GlobalSearchService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class GlobalSearchServiceTest extends Unit
{
    protected UnitTester $tester;

    private GlobalSearchService $search;
    private LearnerService $learners;
    private LessonService $lessons;
    private FinanceService $finance;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, '
            . 'mock_test_faults, mock_tests, expenses, lessons, lesson_series, learner_availability, learners, '
            . 'memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->search = new GlobalSearchService();
        $this->learners = new LearnerService();
        $this->lessons = new LessonService();
        $this->finance = new FinanceService();

        (new AuthService())->register([
            'name' => 'Search Instructor',
            'email' => 'search@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
    }

    public function testFindsPupilByPartialName(): void
    {
        $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700907001',
        ]);

        $result = $this->search->search('sarah');
        $pupils = $result['groups'][0]['items'];
        $this->assertNotEmpty($pupils);
        $this->assertSame('Sarah Ahmed', $pupils[0]['title']);
    }

    public function testTenantIsolation(): void
    {
        $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Local',
            'mobile' => '07700907002',
        ]);

        TenantContext::clear();
        (new AuthService())->register([
            'name' => 'Other Instructor',
            'email' => 'other@example.com',
            'password' => 'password123',
        ]);

        $result = $this->search->search('Sarah');
        $pupils = $result['groups'][0]['items'];
        $this->assertCount(0, $pupils);
    }

    public function testFindsPaymentByPupilName(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Amina',
            'last_name' => 'Yusuf',
            'mobile' => '07700907003',
        ]);
        $this->finance->recordPayment((int) $pupil['id'], [
            'amount_pence' => 4200,
            'method' => 'cash',
            'recorded_at_local' => '2026-09-02 10:00',
        ]);

        $result = $this->search->search('amina');
        $payments = $result['groups'][2]['items'];
        $this->assertNotEmpty($payments);
        $this->assertStringContainsString('£42.00', $payments[0]['title']);
    }

    public function testEmptyQueryReturnsQuickActions(): void
    {
        $result = $this->search->search('');
        $this->assertNotEmpty($result['quick_actions']);
        $this->assertSame([], $result['groups']);
    }

    public function testExpenseSearchBySupplier(): void
    {
        (new BusinessFinanceService())->createExpense([
            'amount_pence' => 6420,
            'category' => 'fuel',
            'spent_on' => '2026-09-03',
            'supplier' => 'Shell',
        ]);

        $result = $this->search->search('shell');
        $expenses = $result['groups'][3]['items'];
        $this->assertNotEmpty($expenses);
        $this->assertStringContainsString('Fuel', $expenses[0]['title']);
    }
}
