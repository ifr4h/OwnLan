<?php

declare(strict_types=1);

namespace app\components\payments;

use Stripe\Account;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Webhook;
use Yii;

/**
 * Stripe Connect Express — direct charges on connected accounts.
 *
 * Architecture: each instructor is merchant of record via Express connected account.
 * OwnLane collects an optional application_fee_amount per payment.
 */
final class StripeGateway implements StripeGatewayInterface
{
    private ?StripeClient $client = null;

    public function isConfigured(): bool
    {
        return $this->secretKey() !== '';
    }

    public function createExpressAccount(string $email, string $returnUrl, string $refreshUrl): array
    {
        $this->bootstrap();
        $account = Account::create([
            'type' => 'express',
            'country' => 'GB',
            'email' => $email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);

        $link = Account::createLoginLink($account->id);
        $onboarding = Account::createAccountLink([
            'account' => $account->id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return [
            'account_id' => $account->id,
            'onboarding_url' => $onboarding->url,
            'dashboard_url' => $link->url ?? null,
        ];
    }

    public function createAccountOnboardingLink(string $accountId, string $returnUrl, string $refreshUrl): array
    {
        $this->bootstrap();
        $onboarding = Account::createAccountLink([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return ['onboarding_url' => $onboarding->url];
    }

    public function retrieveAccount(string $accountId): array
    {
        $this->bootstrap();
        $account = Account::retrieve($accountId);

        $status = 'setup_incomplete';
        if ($account->charges_enabled && $account->payouts_enabled) {
            $status = 'ready';
        } elseif ($account->details_submitted) {
            $status = 'under_review';
        } elseif (!$account->details_submitted) {
            $status = 'setup_incomplete';
        }
        if ($account->requirements?->disabled_reason) {
            $status = 'restricted';
        }

        return [
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'details_submitted' => (bool) $account->details_submitted,
            'status' => $status,
        ];
    }

    public function createCheckoutSession(
        string $connectedAccountId,
        int $amountPence,
        string $currency,
        string $description,
        string $successUrl,
        string $cancelUrl,
        string $idempotencyKey,
        ?int $applicationFeePence = null,
        ?string $paymentIntentId = null,
    ): array {
        $this->bootstrap();
        $params = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $amountPence,
                    'product_data' => ['name' => $description],
                ],
            ]],
            'payment_intent_data' => [
                'application_fee_amount' => $applicationFeePence ?? 0,
            ],
        ];

        $session = Session::create(
            $params,
            [
                'stripe_account' => $connectedAccountId,
                'idempotency_key' => $idempotencyKey,
            ],
        );

        return [
            'session_id' => $session->id,
            'client_secret' => is_string($session->client_secret) ? $session->client_secret : null,
            'url' => $session->url,
            'payment_intent_id' => is_string($session->payment_intent) ? $session->payment_intent : null,
        ];
    }

    public function retrievePaymentIntent(string $connectedAccountId, string $paymentIntentId): array
    {
        $this->bootstrap();
        $intent = PaymentIntent::retrieve($paymentIntentId, [], ['stripe_account' => $connectedAccountId]);
        $chargeId = null;
        if (is_string($intent->latest_charge)) {
            $chargeId = $intent->latest_charge;
        }

        return [
            'payment_intent_id' => $intent->id,
            'status' => (string) $intent->status,
            'charge_id' => $chargeId,
            'amount_pence' => (int) $intent->amount,
        ];
    }

    public function createRefund(
        string $connectedAccountId,
        string $paymentIntentId,
        int $amountPence,
        string $idempotencyKey,
    ): array {
        $this->bootstrap();
        $refund = Refund::create(
            [
                'payment_intent' => $paymentIntentId,
                'amount' => $amountPence,
            ],
            [
                'stripe_account' => $connectedAccountId,
                'idempotency_key' => $idempotencyKey,
            ],
        );

        return [
            'refund_id' => $refund->id,
            'status' => (string) $refund->status,
        ];
    }

    public function constructWebhookEvent(string $payload, string $signatureHeader): object
    {
        $secret = trim((string) (getenv('STRIPE_WEBHOOK_SECRET') ?: ''));
        if ($secret === '') {
            throw new SignatureVerificationException('Webhook secret not configured', $signatureHeader);
        }

        return Webhook::constructEvent($payload, $signatureHeader, $secret);
    }

    private function bootstrap(): void
    {
        if ($this->client !== null) {
            return;
        }
        $key = $this->secretKey();
        if ($key === '') {
            throw new \RuntimeException('Stripe is not configured.');
        }
        Stripe::setApiVersion($this->apiVersion());
        Stripe::setApiKey($key);
        $this->client = new StripeClient(['api_key' => $key]);
    }

    private function secretKey(): string
    {
        return trim((string) (getenv('STRIPE_SECRET_KEY') ?: ''));
    }

    private function apiVersion(): string
    {
        return trim((string) (getenv('STRIPE_API_VERSION') ?: '2024-11-20.acacia'));
    }
}
