<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\Organisation;
use app\models\Payment;
use Yii;

/**
 * Simple payment receipt delivery.
 */
class PaymentReceiptService
{
    private EmailDeliveryService $mail;

    public function __construct(?EmailDeliveryService $mail = null)
    {
        $this->mail = $mail ?? new EmailDeliveryService();
    }

    public function sendIfConfigured(int $paymentId): void
    {
        if (!$this->mail->canDeliver()) {
            return;
        }

        $payment = Payment::findOne(['id' => $paymentId]);
        if ($payment === null || $payment->voided_at !== null) {
            return;
        }

        $cacheKey = 'payment_receipt_sent:' . $paymentId;
        if (Yii::$app->cache->get($cacheKey)) {
            return;
        }

        $learner = Learner::findOne(['id' => (int) $payment->learner_id]);
        $org = Organisation::findOne(['id' => (int) $payment->organisation_id]);
        if ($learner === null || $org === null || !$learner->email) {
            return;
        }

        $amount = Money::formatPence((int) $payment->amount_pence);
        $date = OrganisationTime::formatLocalDisplay(
            OrganisationTime::utcToLocal(new \DateTimeImmutable($payment->recorded_at), $org),
        );

        $this->mail->send(
            (string) $learner->email,
            'Payment received — ' . $amount,
            $learner->first_name . ",\n\nWe received your payment of {$amount} on {$date}.\n\n"
            . "Reference: PAY-" . $payment->id . "\n\n"
            . ($org->name ?? 'Your instructor'),
        );

        Yii::$app->cache->set($cacheKey, 1, 86400 * 30);
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptForPayment(int $paymentId): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $payment = Payment::findOne(['id' => $paymentId, 'organisation_id' => $orgId]);
        if ($payment === null) {
            throw new \yii\web\NotFoundHttpException('Payment not found.');
        }
        $org = Organisation::findOne(['id' => $orgId]);
        $learner = Learner::findOne(['id' => (int) $payment->learner_id]);

        return [
            'reference' => 'PAY-' . $payment->id,
            'amount_label' => Money::formatPence((int) $payment->amount_pence),
            'date_label' => $org ? OrganisationTime::formatLocalDisplay(
                OrganisationTime::utcToLocal(new \DateTimeImmutable($payment->recorded_at), $org),
            ) : $payment->recorded_at,
            'learner_name' => $learner?->getFullName(),
            'method' => $payment->method === Payment::METHOD_CARD ? 'Paid online' : $payment->method,
            'purpose' => $payment->purpose,
            'status' => $payment->payment_status ?? Payment::STATUS_SUCCEEDED,
        ];
    }
}
