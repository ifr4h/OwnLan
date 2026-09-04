<?php

declare(strict_types=1);

namespace app\services;

use app\components\payments\StripeGatewayFactory;
use app\components\TenantContext;
use app\models\InstructorPaymentAccount;
use app\models\LearnerPackage;
use app\models\Organisation;
use app\models\Payment;
use app\models\PaymentAuditLog;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Provider-backed refunds with financial integrity checks.
 */
class PaymentRefundService
{
    /**
     * @return array<string, mixed>
     */
    public function refund(int $paymentId): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $payment = Payment::findOne(['id' => $paymentId, 'organisation_id' => $orgId]);
        if ($payment === null) {
            throw new NotFoundHttpException('Payment not found.');
        }
        if ($payment->voided_at !== null || $payment->payment_status === Payment::STATUS_REFUNDED) {
            throw new BadRequestHttpException('Payment is already refunded or voided.');
        }
        if ($payment->method !== Payment::METHOD_CARD || $payment->provider !== Payment::PROVIDER_STRIPE) {
            throw new BadRequestHttpException('Only online card payments can be refunded here.');
        }
        if ($payment->purpose === Payment::PURPOSE_PACKAGE && $payment->package_id) {
            $package = LearnerPackage::findOne(['id' => (int) $payment->package_id]);
            if ($package !== null && (int) $package->remaining_minutes < (int) $package->purchased_minutes) {
                throw new BadRequestHttpException(
                    'This package has already been used. Refund it manually in Stripe if needed.',
                );
            }
        }

        $account = InstructorPaymentAccount::findOne(['organisation_id' => $orgId]);
        if ($account === null || !$account->provider_account_id || !$payment->provider_payment_id) {
            throw new BadRequestHttpException('Refund cannot be processed.');
        }

        $stripe = StripeGatewayFactory::make();
        $refund = $stripe->createRefund(
            (string) $account->provider_account_id,
            (string) $payment->provider_payment_id,
            (int) $payment->amount_pence,
            'refund:' . $paymentId,
        );

        $now = gmdate('Y-m-d H:i:s');
        $payment->payment_status = Payment::STATUS_REFUNDED;
        $payment->refund_provider_id = $refund['refund_id'];
        $payment->refunded_at = $now;
        $payment->counts_as_income = false;
        $payment->updated_at = $now;
        $payment->save(false);

        if ($payment->package_id) {
            $package = LearnerPackage::findOne(['id' => (int) $payment->package_id]);
            if ($package !== null) {
                $package->status = LearnerPackage::STATUS_VOIDED;
                $package->voided_at = $now;
                $package->void_reason = 'Payment refunded';
                $package->remaining_minutes = 0;
                $package->updated_at = $now;
                $package->save(false);
            }
        }

        $log = new PaymentAuditLog();
        $log->organisation_id = $orgId;
        $log->payment_id = $paymentId;
        $log->action = 'refund_completed';
        $log->actor_user_id = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $log->created_at = $now;
        $log->save(false);

        return [
            'status' => 'refunded',
            'refund_id' => $refund['refund_id'],
            'payment_id' => $paymentId,
        ];
    }
}
