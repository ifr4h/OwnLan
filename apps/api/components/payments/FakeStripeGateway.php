<?php

declare(strict_types=1);

namespace app\components\payments;

use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Stripe test double — no network calls.
 */
final class FakeStripeGateway implements StripeGatewayInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $accounts = [];

    /** @var array<string, array<string, mixed>> */
    private array $sessions = [];

    /** @var array<string, array<string, mixed>> */
    private array $intents = [];

    /** @var array<string, array<string, mixed>> */
    private array $refunds = [];

    private int $counter = 0;

    public function isConfigured(): bool
    {
        return true;
    }

    public function createExpressAccount(string $email, string $returnUrl, string $refreshUrl): array
    {
        $id = 'acct_fake_' . (++$this->counter);
        $this->accounts[$id] = [
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'status' => 'setup_incomplete',
        ];

        return [
            'account_id' => $id,
            'onboarding_url' => 'https://connect.stripe.test/onboard/' . $id,
        ];
    }

    public function createAccountOnboardingLink(string $accountId, string $returnUrl, string $refreshUrl): array
    {
        return ['onboarding_url' => 'https://connect.stripe.test/onboard/' . $accountId];
    }

    public function retrieveAccount(string $accountId): array
    {
        $account = $this->accounts[$accountId] ?? [
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'status' => 'ready',
        ];

        return $account;
    }

    public function markAccountReady(string $accountId): void
    {
        $this->accounts[$accountId] = [
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'status' => 'ready',
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
        $sessionId = 'cs_fake_' . (++$this->counter);
        $intentId = $paymentIntentId ?? ('pi_fake_' . $this->counter);
        $this->sessions[$sessionId] = [
            'connected_account_id' => $connectedAccountId,
            'amount_pence' => $amountPence,
            'idempotency_key' => $idempotencyKey,
            'payment_intent_id' => $intentId,
        ];
        $this->intents[$intentId] = [
            'status' => 'requires_payment_method',
            'amount_pence' => $amountPence,
            'charge_id' => null,
        ];

        return [
            'session_id' => $sessionId,
            'client_secret' => $intentId . '_secret',
            'url' => null,
        ];
    }

    public function simulatePaymentSuccess(string $paymentIntentId, ?string $chargeId = null): void
    {
        $this->intents[$paymentIntentId] = [
            'status' => 'succeeded',
            'amount_pence' => (int) ($this->intents[$paymentIntentId]['amount_pence'] ?? 0),
            'charge_id' => $chargeId ?? ('ch_fake_' . $paymentIntentId),
        ];
    }

    public function retrievePaymentIntent(string $connectedAccountId, string $paymentIntentId): array
    {
        $intent = $this->intents[$paymentIntentId] ?? [
            'status' => 'succeeded',
            'amount_pence' => 0,
            'charge_id' => 'ch_fake',
        ];

        return [
            'payment_intent_id' => $paymentIntentId,
            'status' => (string) $intent['status'],
            'charge_id' => $intent['charge_id'],
            'amount_pence' => (int) $intent['amount_pence'],
        ];
    }

    public function createRefund(
        string $connectedAccountId,
        string $paymentIntentId,
        int $amountPence,
        string $idempotencyKey,
    ): array {
        $refundId = 're_fake_' . (++$this->counter);
        $this->refunds[$idempotencyKey] = [
            'refund_id' => $refundId,
            'status' => 'succeeded',
        ];

        return $this->refunds[$idempotencyKey];
    }

    public function constructWebhookEvent(string $payload, string $signatureHeader): object
    {
        $data = json_decode($payload, false);
        if (!is_object($data)) {
            throw new SignatureVerificationException('Invalid payload', $signatureHeader);
        }
        if ($signatureHeader !== 'valid_test_signature') {
            throw new SignatureVerificationException('Invalid signature', $signatureHeader);
        }

        return $data;
    }
}
