<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\payments\PaymentPlatformFee;
use app\components\payments\StripeGatewayFactory;
use app\components\payments\StripeGatewayInterface;
use app\components\PortalContext;
use app\components\TenantContext;
use app\models\BookingHold;
use app\models\Instructor;
use app\models\InstructorPaymentAccount;
use app\models\Learner;
use app\models\LessonCharge;
use app\models\Organisation;
use app\models\PackageOffering;
use app\models\Payment;
use app\models\PaymentAuditLog;
use app\models\PaymentCheckout;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Online payment checkout — server determines amounts, Stripe is the rail.
 */
class OnlinePaymentService
{
    private StripeGatewayInterface $stripe;
    private FinanceService $finance;
    private BookingHoldService $holds;
    private PaymentReceiptService $receipts;

    public function __construct(
        ?StripeGatewayInterface $stripe = null,
        ?FinanceService $finance = null,
        ?BookingHoldService $holds = null,
        ?PaymentReceiptService $receipts = null,
    ) {
        $this->stripe = $stripe ?? StripeGatewayFactory::make();
        $this->finance = $finance ?? new FinanceService();
        $this->holds = $holds ?? new BookingHoldService();
        $this->receipts = $receipts ?? new PaymentReceiptService();
    }

    /**
     * @return array<string, mixed>
     */
    public function portalPaymentContext(): array
    {
        [$learner, $org] = $this->requirePortalLearner();
        $account = $this->requireReadyAccount($org);
        $owed = $this->finance->amountOwedPence((int) $learner->id);
        $offerings = $this->listPortalOfferings($org);

        return [
            'online_payments_available' => true,
            'amount_owed_pence' => $owed,
            'amount_owed_label' => Money::formatPence($owed),
            'can_pay_balance' => $owed > 0,
            'package_offerings' => $offerings,
            'payment_history' => $this->paymentHistoryForLearner($learner, $org),
            'fee_notice' => PaymentPlatformFee::feeDescription(),
            'stripe_publishable_key' => PaymentPlatformFee::stripePublishableKey(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function startOutstandingCheckout(?int $lessonChargeId = null): array
    {
        [$learner, $org] = $this->requirePortalLearner();
        $account = $this->requireReadyAccount($org);
        $amount = $this->resolveOutstandingAmount((int) $learner->id, $lessonChargeId);
        if ($amount <= 0) {
            throw new BadRequestHttpException('Nothing to pay right now.');
        }

        return $this->createCheckout(
            $org,
            $learner,
            $account,
            PaymentCheckout::PURPOSE_OUTSTANDING,
            $amount,
            $lessonChargeId,
            null,
            null,
            'Outstanding balance',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function startPackageCheckout(int $offeringId): array
    {
        [$learner, $org] = $this->requirePortalLearner();
        $account = $this->requireReadyAccount($org);
        $offering = $this->findOffering($org, $offeringId);
        if (!$offering->portal_visible || !$offering->active) {
            throw new NotFoundHttpException('Package not available.');
        }

        return $this->createCheckout(
            $org,
            $learner,
            $account,
            PaymentCheckout::PURPOSE_PACKAGE,
            (int) $offering->price_pence,
            null,
            (int) $offering->id,
            null,
            $offering->label,
        );
    }

    /**
     * Pay-and-book: checkout for an active booking hold.
     *
     * @return array<string, mixed>
     */
    public function startBookingHoldCheckout(int $holdId): array
    {
        [$learner, $org] = $this->requirePortalLearner();
        $account = $this->requireReadyAccount($org);
        $hold = BookingHold::findOne([
            'id' => $holdId,
            'organisation_id' => (int) $org->id,
            'learner_id' => (int) $learner->id,
        ]);
        if ($hold === null) {
            throw new NotFoundHttpException('Hold not found.');
        }
        if ($hold->status !== BookingHold::STATUS_ACTIVE) {
            throw new BadRequestHttpException('That hold has expired.');
        }
        if ($hold->expires_at !== null && $hold->expires_at < gmdate('Y-m-d H:i:s')) {
            $hold->status = BookingHold::STATUS_EXPIRED;
            $hold->updated_at = gmdate('Y-m-d H:i:s');
            $hold->save(false, ['status', 'updated_at']);
            throw new BadRequestHttpException('That hold has expired.');
        }

        $amount = (int) $hold->price_pence;
        if ($amount <= 0) {
            throw new BadRequestHttpException('Nothing to pay for this booking.');
        }

        $startLocal = OrganisationTime::formatLocalDisplay(
            OrganisationTime::utcToLocal(new DateTimeImmutable((string) $hold->starts_at, new DateTimeZone('UTC')), $org),
        );

        return $this->createCheckout(
            $org,
            $learner,
            $account,
            PaymentCheckout::PURPOSE_BOOKING_HOLD,
            $amount,
            null,
            null,
            (int) $hold->id,
            'Driving lesson · ' . $startLocal,
        );
    }

    /**
     * Instructor requests payment for outstanding balance.
     *
     * @return array<string, mixed>
     */
    public function createPaymentRequest(int $learnerId, ?int $lessonChargeId = null): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }
        $learner = $this->findOwnedLearner($org, $learnerId);
        $account = $this->requireReadyAccount($org);
        $amount = $this->resolveOutstandingAmount($learnerId, $lessonChargeId);
        if ($amount <= 0) {
            throw new BadRequestHttpException('This pupil has nothing outstanding.');
        }

        $checkout = $this->createCheckout(
            $org,
            $learner,
            $account,
            PaymentCheckout::PURPOSE_PAYMENT_REQUEST,
            $amount,
            $lessonChargeId,
            null,
            null,
            'Lesson payment',
            forGuest: true,
        );

        $base = rtrim((string) (getenv('WEB_URL') ?: 'http://127.0.0.1:3000'), '/');
        $checkout['payment_url'] = $base . '/pay/' . $checkout['token'];

        return $checkout;
    }

    /**
     * Guest-scoped payment page — token only, no portal session.
     *
     * @return array<string, mixed>
     */
    public function guestCheckout(string $token): array
    {
        $checkout = $this->findCheckoutByToken($token);
        if (!in_array($checkout->purpose, [
            PaymentCheckout::PURPOSE_PAYMENT_REQUEST,
        ], true)) {
            throw new NotFoundHttpException('Payment link not found.');
        }
        $this->assertCheckoutPayable($checkout);

        $org = Organisation::findOne(['id' => (int) $checkout->organisation_id]);
        $learner = Learner::findOne(['id' => (int) $checkout->learner_id]);
        if ($org === null || $learner === null) {
            throw new NotFoundHttpException('Payment link not found.');
        }

        return $this->serializeCheckout($checkout, $org, $learner, scoped: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function guestStartPayment(string $token): array
    {
        $checkout = $this->findCheckoutByToken($token);
        $org = Organisation::findOne(['id' => (int) $checkout->organisation_id]);
        $learner = Learner::findOne(['id' => (int) $checkout->learner_id]);
        if ($org === null || $learner === null) {
            throw new NotFoundHttpException('Payment link not found.');
        }
        $account = $this->requireReadyAccount($org);
        $this->assertCheckoutPayable($checkout);
        $this->revalidateCheckoutAmount($checkout, $learner);

        return $this->attachStripeSession($checkout, $org, $learner, $account);
    }

    /**
     * Portal learner confirms checkout session after Stripe redirect.
     *
     * @return array<string, mixed>
     */
    public function confirmCheckout(string $token): array
    {
        [$learner] = $this->requirePortalLearner();
        $checkout = $this->findCheckoutByToken($token);
        if ((int) $checkout->learner_id !== (int) $learner->id) {
            throw new ForbiddenHttpException('This payment does not belong to you.');
        }

        return $this->reconcileCheckout($checkout);
    }

    /**
     * Guest-scoped confirm after Stripe redirect — token only.
     *
     * @return array<string, mixed>
     */
    public function guestConfirmCheckout(string $token): array
    {
        $checkout = $this->findCheckoutByToken($token);
        if (!in_array($checkout->purpose, [
            PaymentCheckout::PURPOSE_PAYMENT_REQUEST,
        ], true)) {
            throw new NotFoundHttpException('Payment not found.');
        }

        return $this->reconcileCheckout($checkout);
    }

    /**
     * Idempotent fulfillment from verified provider state.
     *
     * @return array<string, mixed>
     */
    public function fulfillCheckout(PaymentCheckout $checkout, string $paymentIntentId, string $chargeId): array
    {
        if ($checkout->status === PaymentCheckout::STATUS_SUCCEEDED && $checkout->payment_id) {
            return ['status' => 'already_fulfilled', 'payment_id' => (int) $checkout->payment_id];
        }

        $org = Organisation::findOne(['id' => (int) $checkout->organisation_id]);
        if ($org === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }
        $account = InstructorPaymentAccount::findOne(['organisation_id' => (int) $org->id]);
        if ($account === null || $account->provider_account_id === null) {
            throw new BadRequestHttpException('Payment account not configured.');
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $checkout->refresh();
            if ($checkout->status === PaymentCheckout::STATUS_SUCCEEDED && $checkout->payment_id) {
                $tx->rollBack();

                return ['status' => 'already_fulfilled', 'payment_id' => (int) $checkout->payment_id];
            }

            $this->revalidateCheckoutAmount($checkout, Learner::findOne(['id' => (int) $checkout->learner_id]));

            $platformFee = PaymentPlatformFee::applicationFeePence((int) $checkout->amount_pence);
            $result = $this->finance->applyOnlinePayment([
                'learner_id' => (int) $checkout->learner_id,
                'amount_pence' => (int) $checkout->amount_pence,
                'purpose' => $checkout->purpose === PaymentCheckout::PURPOSE_PACKAGE
                    ? Payment::PURPOSE_PACKAGE
                    : Payment::PURPOSE_LESSON_BALANCE,
                'lesson_charge_id' => $checkout->lesson_charge_id,
                'package_offering_id' => $checkout->package_offering_id,
                'idempotency_key' => 'checkout:' . (int) $checkout->id,
                'provider' => Payment::PROVIDER_STRIPE,
                'provider_payment_id' => $paymentIntentId,
                'provider_charge_id' => $chargeId,
                'provider_status' => 'succeeded',
                'platform_fee_pence' => $platformFee,
                'net_pence' => (int) $checkout->amount_pence - $platformFee,
            ], $org);

            $payment = $result['payment'];
            $checkout->payment_id = (int) $payment['id'];
            $checkout->provider_payment_intent_id = $paymentIntentId;
            $checkout->status = PaymentCheckout::STATUS_SUCCEEDED;
            $checkout->updated_at = gmdate('Y-m-d H:i:s');
            $checkout->save(false);

            if ($checkout->booking_hold_id) {
                try {
                    $this->holds->convertToLesson((int) $checkout->booking_hold_id, (int) $payment['id']);
                } catch (ConflictHttpException $e) {
                    $this->audit($org, (int) $payment['id'], (int) $checkout->id, 'booking_hold_expired_after_payment');
                }
            }

            $this->audit($org, (int) $payment['id'], (int) $checkout->id, 'payment_succeeded');
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $this->receipts->sendIfConfigured((int) $payment['id']);

        return [
            'status' => 'fulfilled',
            'payment' => $payment,
            'checkout' => $this->serializeCheckout($checkout, $org, Learner::findOne(['id' => (int) $checkout->learner_id])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reconcileCheckout(PaymentCheckout $checkout): array
    {
        if ($checkout->status === PaymentCheckout::STATUS_SUCCEEDED) {
            return [
                'status' => 'succeeded',
                'checkout' => $this->serializeCheckout(
                    $checkout,
                    Organisation::findOne(['id' => (int) $checkout->organisation_id]),
                    Learner::findOne(['id' => (int) $checkout->learner_id]),
                ),
            ];
        }

        if ($checkout->provider_payment_intent_id === null) {
            return ['status' => 'processing'];
        }

        $org = Organisation::findOne(['id' => (int) $checkout->organisation_id]);
        $account = InstructorPaymentAccount::findOne(['organisation_id' => (int) $checkout->organisation_id]);
        if ($org === null || $account?->provider_account_id === null) {
            throw new BadRequestHttpException('Payment account not available.');
        }

        $intent = $this->stripe->retrievePaymentIntent(
            (string) $account->provider_account_id,
            (string) $checkout->provider_payment_intent_id,
        );

        if ($intent['status'] === 'succeeded') {
            return $this->fulfillCheckout(
                $checkout,
                (string) $intent['payment_intent_id'],
                (string) ($intent['charge_id'] ?? ''),
            );
        }

        if (in_array($intent['status'], ['processing', 'requires_capture'], true)) {
            $checkout->status = PaymentCheckout::STATUS_PROCESSING;
            $checkout->updated_at = gmdate('Y-m-d H:i:s');
            $checkout->save(false, ['status', 'updated_at']);

            return ['status' => 'processing'];
        }

        return ['status' => 'failed', 'message' => 'Payment didn\'t go through.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCheckout(
        Organisation $org,
        Learner $learner,
        InstructorPaymentAccount $account,
        string $purpose,
        int $amountPence,
        ?int $lessonChargeId,
        ?int $packageOfferingId,
        ?int $bookingHoldId,
        string $description,
        bool $forGuest = false,
    ): array {
        $token = bin2hex(random_bytes(24));
        $idempotencyKey = hash('sha256', implode('|', [
            (int) $org->id,
            (int) $learner->id,
            $purpose,
            $amountPence,
            $lessonChargeId ?? '',
            $packageOfferingId ?? '',
            $bookingHoldId ?? '',
            microtime(true),
        ]));
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + 3600);

        $checkout = new PaymentCheckout();
        $checkout->organisation_id = (int) $org->id;
        $checkout->learner_id = (int) $learner->id;
        $checkout->token = $token;
        $checkout->purpose = $purpose;
        $checkout->amount_pence = $amountPence;
        $checkout->currency = 'gbp';
        $checkout->lesson_charge_id = $lessonChargeId;
        $checkout->package_offering_id = $packageOfferingId;
        $checkout->booking_hold_id = $bookingHoldId;
        $checkout->status = PaymentCheckout::STATUS_CREATED;
        $checkout->idempotency_key = $idempotencyKey;
        $checkout->expires_at = $expires;
        $checkout->created_at = $now;
        $checkout->updated_at = $now;
        if (!$checkout->save()) {
            throw new BadRequestHttpException('Could not start payment.');
        }

        $session = $this->attachStripeSession($checkout, $org, $learner, $account, $description);

        return array_merge($session, [
            'token' => $token,
            'amount_pence' => $amountPence,
            'amount_label' => Money::formatPence($amountPence),
            'purpose' => $purpose,
            'for_guest' => $forGuest,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attachStripeSession(
        PaymentCheckout $checkout,
        Organisation $org,
        Learner $learner,
        InstructorPaymentAccount $account,
        ?string $description = null,
    ): array {
        $base = rtrim((string) (getenv('WEB_URL') ?: 'http://127.0.0.1:3000'), '/');
        $successUrl = $checkout->purpose === PaymentCheckout::PURPOSE_PAYMENT_REQUEST
            ? $base . '/pay/' . $checkout->token . '/success'
            : $base . '/portal/pay/' . $checkout->token . '/success';
        $cancelUrl = $checkout->purpose === PaymentCheckout::PURPOSE_PAYMENT_REQUEST
            ? $base . '/pay/' . $checkout->token
            : $base . '/portal/pay/' . $checkout->token;

        $fee = PaymentPlatformFee::applicationFeePence((int) $checkout->amount_pence);
        $session = $this->stripe->createCheckoutSession(
            (string) $account->provider_account_id,
            (int) $checkout->amount_pence,
            (string) $checkout->currency,
            $description ?? 'Driving lesson payment',
            $successUrl,
            $cancelUrl,
            (string) $checkout->idempotency_key,
            $fee,
            $checkout->provider_payment_intent_id,
        );

        $checkout->provider_checkout_session_id = $session['session_id'];
        if (!empty($session['payment_intent_id'])) {
            $checkout->provider_payment_intent_id = $session['payment_intent_id'];
        }
        $checkout->status = PaymentCheckout::STATUS_PROCESSING;
        $checkout->updated_at = gmdate('Y-m-d H:i:s');
        $checkout->save(false);

        return [
            'checkout_id' => (int) $checkout->id,
            'client_secret' => $session['client_secret'],
            'checkout_url' => $session['url'],
            'stripe_publishable_key' => PaymentPlatformFee::stripePublishableKey(),
            'expires_at' => $checkout->expires_at,
        ];
    }

    private function resolveOutstandingAmount(int $learnerId, ?int $lessonChargeId): int
    {
        if ($lessonChargeId !== null) {
            $charge = LessonCharge::findOne([
                'id' => $lessonChargeId,
                'learner_id' => $learnerId,
                'status' => LessonCharge::STATUS_OUTSTANDING,
            ]);
            if ($charge === null) {
                return 0;
            }

            return $charge->outstandingPence();
        }

        return $this->finance->amountOwedPence($learnerId);
    }

    private function revalidateCheckoutAmount(PaymentCheckout $checkout, ?Learner $learner): void
    {
        if ($learner === null) {
            throw new NotFoundHttpException('Learner not found.');
        }
        $expected = match ($checkout->purpose) {
            PaymentCheckout::PURPOSE_PACKAGE => PackageOffering::findOne(['id' => (int) $checkout->package_offering_id])?->price_pence ?? 0,
            default => $this->resolveOutstandingAmount(
                (int) $learner->id,
                $checkout->lesson_charge_id ? (int) $checkout->lesson_charge_id : null,
            ),
        };
        if ((int) $expected !== (int) $checkout->amount_pence || (int) $expected <= 0) {
            throw new BadRequestHttpException('This payment is no longer valid.');
        }
    }

    private function assertCheckoutPayable(PaymentCheckout $checkout): void
    {
        if (in_array($checkout->status, [PaymentCheckout::STATUS_SUCCEEDED, PaymentCheckout::STATUS_CANCELLED], true)) {
            throw new BadRequestHttpException('This payment has already been completed.');
        }
        if ($checkout->expires_at !== null && $checkout->expires_at < gmdate('Y-m-d H:i:s')) {
            $checkout->status = PaymentCheckout::STATUS_EXPIRED;
            $checkout->updated_at = gmdate('Y-m-d H:i:s');
            $checkout->save(false, ['status', 'updated_at']);
            throw new BadRequestHttpException('This payment link has expired.');
        }
    }

    private function findCheckoutByToken(string $token): PaymentCheckout
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 20) {
            throw new NotFoundHttpException('Payment not found.');
        }
        $checkout = PaymentCheckout::findOne(['token' => $token]);
        if ($checkout === null) {
            throw new NotFoundHttpException('Payment not found.');
        }

        return $checkout;
    }

    private function requireReadyAccount(Organisation $org): InstructorPaymentAccount
    {
        $account = InstructorPaymentAccount::findOne(['organisation_id' => (int) $org->id]);
        if ($account === null || $account->status !== InstructorPaymentAccount::STATUS_READY || !$account->charges_enabled) {
            throw new BadRequestHttpException('Online payments are not available for this instructor.');
        }

        return $account;
    }

    /**
     * @return array{0: Learner, 1: Organisation}
     */
    private function requirePortalLearner(): array
    {
        $learnerId = PortalContext::requireLearnerId();
        $orgId = PortalContext::requireOrganisationId();
        $learner = Learner::findOne(['id' => $learnerId, 'organisation_id' => $orgId]);
        $org = Organisation::findOne(['id' => $orgId]);
        if ($learner === null || $org === null) {
            throw new ForbiddenHttpException('Portal session required.');
        }

        return [$learner, $org];
    }

    private function findOwnedLearner(Organisation $org, int $learnerId): Learner
    {
        $learner = Learner::findOne(['id' => $learnerId, 'organisation_id' => (int) $org->id]);
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }

    private function findOffering(Organisation $org, int $id): PackageOffering
    {
        $offering = PackageOffering::findOne(['id' => $id, 'organisation_id' => (int) $org->id]);
        if ($offering === null) {
            throw new NotFoundHttpException('Package not found.');
        }

        return $offering;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listPortalOfferings(Organisation $org): array
    {
        /** @var PackageOffering[] $rows */
        $rows = PackageOffering::find()
            ->andWhere(['organisation_id' => (int) $org->id, 'active' => true, 'portal_visible' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(static fn (PackageOffering $o) => [
            'id' => (int) $o->id,
            'label' => $o->label,
            'purchased_minutes' => (int) $o->purchased_minutes,
            'hours_label' => self::hoursLabel((int) $o->purchased_minutes),
            'price_pence' => (int) $o->price_pence,
            'price_label' => Money::formatPence((int) $o->price_pence),
        ], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentHistoryForLearner(Learner $learner, Organisation $org): array
    {
        /** @var Payment[] $payments */
        $payments = Payment::find()
            ->andWhere(['learner_id' => (int) $learner->id, 'organisation_id' => (int) $org->id])
            ->andWhere(['voided_at' => null])
            ->orderBy(['recorded_at' => SORT_DESC])
            ->limit(20)
            ->all();

        return array_map(function (Payment $p) use ($org) {
            return [
                'id' => (int) $p->id,
                'amount_label' => Money::formatPence((int) $p->amount_pence),
                'method_label' => $p->method === Payment::METHOD_CARD ? 'Paid online' : ucfirst(str_replace('_', ' ', $p->method)),
                'status' => $p->payment_status ?? Payment::STATUS_SUCCEEDED,
                'date_label' => OrganisationTime::formatLocalDisplay(
                    OrganisationTime::utcToLocal(new DateTimeImmutable($p->recorded_at, new DateTimeZone('UTC')), $org),
                ),
                'purpose' => $p->purpose,
            ];
        }, $payments);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCheckout(
        PaymentCheckout $checkout,
        ?Organisation $org,
        ?Learner $learner,
        bool $scoped = false,
    ): array {
        $instructor = $org ? Instructor::find()->andWhere(['organisation_id' => (int) $org->id])->one() : null;

        return [
            'token' => $checkout->token,
            'amount_pence' => (int) $checkout->amount_pence,
            'amount_label' => Money::formatPence((int) $checkout->amount_pence),
            'status' => $checkout->status,
            'purpose' => $checkout->purpose,
            'business_name' => $instructor?->display_name ?? $org?->name,
            'description' => $this->checkoutDescription($checkout),
            'expires_at' => $checkout->expires_at,
            'scoped' => $scoped,
            'learner_first_name' => $scoped ? null : $learner?->first_name,
        ];
    }

    private function checkoutDescription(PaymentCheckout $checkout): string
    {
        return match ($checkout->purpose) {
            PaymentCheckout::PURPOSE_PACKAGE => PackageOffering::findOne(['id' => (int) $checkout->package_offering_id])?->label ?? 'Package',
            PaymentCheckout::PURPOSE_BOOKING_HOLD => 'Driving lesson',
            default => 'Outstanding balance',
        };
    }

    private function audit(Organisation $org, ?int $paymentId, ?int $checkoutId, string $action): void
    {
        $log = new PaymentAuditLog();
        $log->organisation_id = (int) $org->id;
        $log->payment_id = $paymentId;
        $log->checkout_id = $checkoutId;
        $log->action = $action;
        $log->actor_user_id = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $log->created_at = gmdate('Y-m-d H:i:s');
        $log->save(false);
    }

    private static function hoursLabel(int $minutes): string
    {
        $hours = $minutes / 60;
        if (fmod($hours, 1.0) === 0.0) {
            return ((int) $hours) . ' hours';
        }

        return rtrim(rtrim(number_format($hours, 1), '0'), '.') . ' hours';
    }
}
