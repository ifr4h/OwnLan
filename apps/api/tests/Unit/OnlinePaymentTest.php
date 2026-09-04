<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\payments\FakeStripeGateway;
use app\components\TenantContext;
use app\models\InstructorPaymentAccount;
use app\models\LearnerPackage;
use app\models\LessonCharge;
use app\models\Organisation;
use app\models\Payment;
use app\models\PaymentCheckout;
use app\services\AuthService;
use app\services\FinanceService;
use app\services\LearnerService;
use app\services\OnlinePaymentService;
use app\services\StripeWebhookService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class OnlinePaymentTest extends Unit
{
    protected UnitTester $tester;

    private Organisation $org;
    private FakeStripeGateway $stripe;

    protected function _before(): void
    {
        Yii::$app->set('stripeGateway', new FakeStripeGateway());
        $this->stripe = Yii::$app->get('stripeGateway');

        Yii::$app->db->createCommand(
            'TRUNCATE payment_audit_log, stripe_webhook_events, booking_holds, payment_checkouts, package_offerings, '
            . 'instructor_payment_accounts, payment_allocations, package_credit_usages, lesson_charges, '
            . 'payments, learner_packages, lessons, lesson_series, learner_availability, learners, learner_intakes, '
            . 'memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();

        (new AuthService())->register([
            'name' => 'Amina Yusuf',
            'email' => 'amina-payments@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->seedPaymentAccount();
    }

    public function testApplyOnlinePaymentSettlesOutstandingBalance(): void
    {
        $learner = $this->createLearnerWithCharge(6300);
        $finance = new FinanceService();
        $result = $finance->applyOnlinePayment([
            'learner_id' => (int) $learner->id,
            'amount_pence' => 6300,
            'purpose' => Payment::PURPOSE_LESSON_BALANCE,
            'lesson_charge_id' => LessonCharge::find()->one()->id,
            'idempotency_key' => 'test:balance:1',
            'provider_payment_id' => 'pi_test_1',
            'provider_charge_id' => 'ch_test_1',
        ], $this->org);

        $this->assertSame(6300, $result['allocated_pence']);
        $this->assertSame(0, (new FinanceService())->amountOwedPence((int) $learner->id));
    }

    public function testIdempotentCheckoutFulfillment(): void
    {
        $learner = $this->createLearnerWithCharge(4200);
        $checkout = $this->makeCheckout((int) $learner->id, 4200);
        $service = new OnlinePaymentService($this->stripe);

        $this->stripe->simulatePaymentSuccess('pi_dup_test', 'ch_dup');
        $checkout->provider_payment_intent_id = 'pi_dup_test';
        $checkout->save(false);

        $first = $service->fulfillCheckout($checkout, 'pi_dup_test', 'ch_dup');
        $second = $service->fulfillCheckout($checkout, 'pi_dup_test', 'ch_dup');

        $this->assertSame('fulfilled', $first['status']);
        $this->assertSame('already_fulfilled', $second['status']);
        $this->assertSame(1, (int) Payment::find()->count());
    }

    public function testPackagePurchaseCreditsOnce(): void
    {
        $learner = $this->createLearner();
        Yii::$app->db->createCommand()->insert('{{%package_offerings}}', [
            'organisation_id' => (int) $this->org->id,
            'label' => '10 hours',
            'purchased_minutes' => 600,
            'price_pence' => 40000,
            'active' => true,
            'portal_visible' => true,
            'sort_order' => 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
        $offeringId = (int) Yii::$app->db->getLastInsertID();

        $finance = new FinanceService();
        $finance->applyOnlinePayment([
            'learner_id' => (int) $learner->id,
            'amount_pence' => 40000,
            'purpose' => Payment::PURPOSE_PACKAGE,
            'package_offering_id' => $offeringId,
            'idempotency_key' => 'test:package:1',
            'provider_payment_id' => 'pi_pkg_1',
        ], $this->org);

        $finance->applyOnlinePayment([
            'learner_id' => (int) $learner->id,
            'amount_pence' => 40000,
            'purpose' => Payment::PURPOSE_PACKAGE,
            'package_offering_id' => $offeringId,
            'idempotency_key' => 'test:package:1',
            'provider_payment_id' => 'pi_pkg_1',
        ], $this->org);

        $this->assertSame(1, (int) Payment::find()->count());
        $this->assertSame(1, (int) LearnerPackage::find()->count());
        $this->assertSame(600, (new FinanceService())->remainingCreditMinutes((int) $learner->id));
    }

    public function testWebhookIdempotency(): void
    {
        $learner = $this->createLearnerWithCharge(6300);
        $checkout = $this->makeCheckout((int) $learner->id, 6300);
        $checkout->provider_payment_intent_id = 'pi_webhook_1';
        $checkout->save(false);

        $payload = json_encode([
            'id' => 'evt_test_1',
            'type' => 'payment_intent.succeeded',
            'data' => (object) ['object' => (object) [
                'id' => 'pi_webhook_1',
                'latest_charge' => 'ch_webhook_1',
            ]],
        ]);
        $this->stripe->simulatePaymentSuccess('pi_webhook_1', 'ch_webhook_1');

        $webhook = new StripeWebhookService(new OnlinePaymentService($this->stripe));
        $webhook->handle((string) $payload, 'valid_test_signature');
        $webhook->handle((string) $payload, 'valid_test_signature');

        $this->assertSame(1, (int) Payment::find()->count());
    }

    public function testTamperedAmountRejectedOnFulfillment(): void
    {
        $learner = $this->createLearnerWithCharge(6300);
        $checkout = $this->makeCheckout((int) $learner->id, 100);
        $checkout->provider_payment_intent_id = 'pi_bad_amount';
        $checkout->save(false);
        $this->stripe->simulatePaymentSuccess('pi_bad_amount');

        $this->expectException(\yii\web\BadRequestHttpException::class);
        (new OnlinePaymentService($this->stripe))->fulfillCheckout($checkout, 'pi_bad_amount', 'ch_x');
    }

    private function seedPaymentAccount(): void
    {
        $account = new InstructorPaymentAccount();
        $account->organisation_id = (int) $this->org->id;
        $account->instructor_id = 1;
        $account->provider = 'stripe';
        $account->provider_account_id = 'acct_test_amina';
        $account->status = InstructorPaymentAccount::STATUS_READY;
        $account->charges_enabled = true;
        $account->payouts_enabled = true;
        $account->details_submitted = true;
        $account->created_at = gmdate('Y-m-d H:i:s');
        $account->updated_at = gmdate('Y-m-d H:i:s');
        $account->save(false);
        $this->stripe->markAccountReady('acct_test_amina');
    }

    private function createLearner(): \app\models\Learner
    {
        $payload = (new LearnerService())->create([
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700900421',
        ]);

        return \app\models\Learner::findOne(['id' => (int) $payload['id']]);
    }

    private function createLearnerWithCharge(int $pence): \app\models\Learner
    {
        $learner = $this->createLearner();
        $lesson = new \app\models\Lesson();
        $lesson->organisation_id = (int) $this->org->id;
        $lesson->instructor_id = 1;
        $lesson->learner_id = (int) $learner->id;
        $lesson->status = 'completed';
        $lesson->duration_minutes = 90;
        $lesson->starts_at = gmdate('Y-m-d H:i:s', strtotime('-1 day'));
        $lesson->created_at = gmdate('Y-m-d H:i:s');
        $lesson->updated_at = gmdate('Y-m-d H:i:s');
        $lesson->save(false);

        $charge = new LessonCharge();
        $charge->organisation_id = (int) $this->org->id;
        $charge->learner_id = (int) $learner->id;
        $charge->lesson_id = (int) $lesson->id;
        $charge->amount_pence = $pence;
        $charge->amount_paid_pence = 0;
        $charge->status = LessonCharge::STATUS_OUTSTANDING;
        $charge->created_at = gmdate('Y-m-d H:i:s');
        $charge->updated_at = gmdate('Y-m-d H:i:s');
        $charge->save(false);

        return $learner;
    }

    private function makeCheckout(int $learnerId, int $amountPence): PaymentCheckout
    {
        $checkout = new PaymentCheckout();
        $checkout->organisation_id = (int) $this->org->id;
        $checkout->learner_id = $learnerId;
        $checkout->token = bin2hex(random_bytes(16));
        $checkout->purpose = PaymentCheckout::PURPOSE_OUTSTANDING;
        $checkout->amount_pence = $amountPence;
        $checkout->currency = 'gbp';
        $checkout->status = PaymentCheckout::STATUS_PROCESSING;
        $checkout->idempotency_key = 'key:' . bin2hex(random_bytes(8));
        $checkout->expires_at = gmdate('Y-m-d H:i:s', time() + 3600);
        $checkout->created_at = gmdate('Y-m-d H:i:s');
        $checkout->updated_at = gmdate('Y-m-d H:i:s');
        $checkout->save(false);

        return $checkout;
    }
}
