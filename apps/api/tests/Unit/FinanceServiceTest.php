<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\FinanceService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class FinanceServiceTest extends Unit
{
    protected UnitTester $tester;

    private FinanceService $finance;
    private LessonService $lessons;
    private LearnerService $learners;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->finance = new FinanceService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Finance Instructor',
            'email' => 'finance@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->org->default_hourly_rate_pence = 3500; // £35/hour
        $this->org->save(false, ['default_hourly_rate_pence']);
    }

    public function testCreatePackageWithPaymentAndCreditSummary(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Pat',
            'last_name' => 'Package',
            'mobile' => '07700903001',
        ]);

        $result = $this->finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 10,
            'price_pence' => 32000,
            'record_payment' => true,
            'payment_method' => 'bank_transfer',
            'label' => '10-hour block',
        ]);

        $this->assertSame(600, $result['package']['purchased_minutes']);
        $this->assertSame(600, $result['package']['remaining_minutes']);
        $this->assertSame(32000, $result['package']['price_pence']);
        $this->assertSame('£320.00', $result['package']['price_label']);
        $this->assertNotNull($result['payment']);
        $this->assertSame(32000, $result['payment']['amount_pence']);
        $this->assertTrue($result['payment']['counts_as_income']);
        $this->assertSame(600, $result['summary']['credit_minutes']);
        $this->assertFalse($result['summary']['owes_money']);
        $this->assertSame('10 hours left', $result['summary']['credit_label']);
    }

    public function testCompleteLessonConsumesPackageCreditNotCharge(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Chris',
            'last_name' => 'Credit',
            'mobile' => '07700903002',
        ]);
        $this->finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 2,
            'price_pence' => 7000,
            'record_payment' => true,
        ]);

        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-10 10:00',
            'duration_minutes' => 60,
        ]);
        $done = $this->lessons->complete((int) $lesson['id'], [
            'learner_summary' => 'Good mirrors',
        ]);

        $this->assertSame('package', $done['settlement']);
        $this->assertSame('package', $done['finance']['settlement']);
        $this->assertNotNull($done['finance']['package_usage']);
        $this->assertNull($done['finance']['charge']);
        $this->assertSame('Chris', $done['finance']['aftermath']['learner_first_name']);
        $this->assertSame('1 hour remaining', $done['finance']['aftermath']['credit_line']);
        $this->assertSame('book_next', $done['finance']['aftermath']['primary_cta']);
        $this->assertSame('Book next lesson', $done['finance']['aftermath']['primary_cta_label']);

        $summary = $this->finance->summaryForLearner((int) $pupil['id']);
        $this->assertSame(60, $summary['credit_minutes']);
        $this->assertSame(0, $summary['amount_owed_pence']);
        $this->assertFalse($summary['owes_money']);
    }

    public function testCompleteWithoutCreditCreatesOutstandingThenPaymentClears(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Owen',
            'last_name' => 'Owes',
            'mobile' => '07700903003',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-10 11:00',
            'duration_minutes' => 60,
        ]);
        $done = $this->lessons->complete((int) $lesson['id'], [
            'settlement' => 'charge',
        ]);

        $this->assertSame('outstanding', $done['settlement']);
        $this->assertSame(3500, $done['finance']['charge']['amount_pence']);
        $this->assertSame('No prepaid credit remaining', $done['finance']['aftermath']['credit_line']);
        $this->assertSame('record_payment', $done['finance']['aftermath']['primary_cta']);
        $this->assertSame('Record payment', $done['finance']['aftermath']['primary_cta_label']);
        $this->assertSame('book_next', $done['finance']['aftermath']['secondary_cta']);

        $summary = $this->finance->summaryForLearner((int) $pupil['id']);
        $this->assertTrue($summary['owes_money']);
        $this->assertSame(3500, $summary['amount_owed_pence']);
        $this->assertSame('Owes £35.00', $summary['money_status_label']);

        $pay = $this->finance->recordPayment((int) $pupil['id'], [
            'amount_pence' => 3500,
            'method' => 'cash',
        ]);
        $this->assertSame(3500, $pay['allocated_pence']);
        $this->assertFalse($pay['summary']['owes_money']);
        $this->assertSame(0, $pay['summary']['amount_owed_pence']);
    }

    public function testVoidPaymentRestoresOutstandingWithAuditReason(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Vera',
            'last_name' => 'Void',
            'mobile' => '07700903004',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-11 09:00',
            'duration_minutes' => 90,
        ]);
        $this->lessons->complete((int) $lesson['id'], [
            'settlement' => 'paid',
            'payment_method' => 'cash',
        ]);

        $panel = $this->finance->panelForLearner((int) $pupil['id']);
        $this->assertFalse($panel['summary']['owes_money']);

        $paymentId = null;
        foreach ($panel['history'] as $row) {
            if ($row['kind'] === 'payment_received') {
                $paymentId = $row['payment_id'];
                break;
            }
        }
        $this->assertNotNull($paymentId);

        $voided = $this->finance->voidPayment((int) $paymentId, [
            'reason' => 'Entered wrong amount',
        ]);
        $this->assertNotNull($voided['payment']['voided_at']);
        $this->assertSame('Entered wrong amount', $voided['payment']['void_reason']);
        $this->assertFalse($voided['payment']['counts_as_income']);
        $this->assertTrue($voided['summary']['owes_money']);
        // 90 min at £35/hour = £52.50
        $this->assertSame(5250, $voided['summary']['amount_owed_pence']);
    }

    public function testHistoryKeepsConceptsSeparate(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Hist',
            'last_name' => 'Ory',
            'mobile' => '07700903005',
        ]);
        $this->finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 1,
            'price_pence' => 3500,
            'record_payment' => true,
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-12 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->complete((int) $lesson['id']);

        $kinds = array_column($this->finance->history((int) $pupil['id']), 'kind');
        $this->assertContains('package_purchased', $kinds);
        $this->assertContains('payment_received', $kinds);
        $this->assertContains('credit_consumed', $kinds);
        $this->assertNotContains('lesson_charge', $kinds);
    }

    public function testRejectsFloatingPointPrice(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Float',
            'last_name' => 'Bad',
            'mobile' => '07700903006',
        ]);

        $this->expectException(\yii\web\BadRequestHttpException::class);
        $this->finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 5,
            'price_pence' => 175.5,
        ]);
    }
}
