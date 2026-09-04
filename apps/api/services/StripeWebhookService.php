<?php

declare(strict_types=1);

namespace app\services;

use app\models\PaymentCheckout;
use app\models\StripeWebhookEvent;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Verified Stripe webhook processing with idempotency.
 */
class StripeWebhookService
{
    private OnlinePaymentService $payments;

    public function __construct(?OnlinePaymentService $payments = null)
    {
        $this->payments = $payments ?? new OnlinePaymentService();
    }

    public function handle(string $payload, string $signature): array
    {
        $gateway = \app\components\payments\StripeGatewayFactory::make();
        try {
            $event = $gateway->constructWebhookEvent($payload, $signature);
        } catch (\Throwable $e) {
            throw new BadRequestHttpException('Invalid webhook signature.');
        }

        $eventId = (string) ($event->id ?? '');
        if ($eventId === '') {
            throw new BadRequestHttpException('Invalid webhook event.');
        }

        if (StripeWebhookEvent::findOne(['event_id' => $eventId]) !== null) {
            return ['status' => 'duplicate'];
        }

        $type = (string) ($event->type ?? '');
        $checkoutId = null;
        $result = ['status' => 'ignored', 'type' => $type];

        if ($type === 'payment_intent.succeeded') {
            $intent = $event->data->object ?? null;
            $intentId = is_object($intent) ? (string) ($intent->id ?? '') : '';
            $chargeId = is_object($intent) ? (string) ($intent->latest_charge ?? '') : '';
            $checkout = PaymentCheckout::findOne(['provider_payment_intent_id' => $intentId]);
            if ($checkout !== null) {
                $checkoutId = (int) $checkout->id;
                $result = $this->payments->fulfillCheckout($checkout, $intentId, $chargeId);
            }
        }

        $row = new StripeWebhookEvent();
        $row->event_id = $eventId;
        $row->event_type = $type;
        $row->processed_at = gmdate('Y-m-d H:i:s');
        $row->payment_checkout_id = $checkoutId;
        $row->save(false);

        return $result;
    }
}
