<?php

declare(strict_types=1);

namespace app\components\payments;

/**
 * Stripe Connect + Checkout operations — swappable for tests.
 */
interface StripeGatewayInterface
{
    public function isConfigured(): bool;

    /**
     * @return array{account_id: string, onboarding_url: string}
     */
    public function createExpressAccount(string $email, string $returnUrl, string $refreshUrl): array;

    /**
     * @return array{charges_enabled: bool, payouts_enabled: bool, details_submitted: bool, status: string}
     */
    public function retrieveAccount(string $accountId): array;

    /**
     * @return array{onboarding_url: string}
     */
    public function createAccountOnboardingLink(string $accountId, string $returnUrl, string $refreshUrl): array;

    /**
     * @return array{session_id: string, client_secret: string|null, url: string|null}
     */
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
    ): array;

    /**
     * @return array{payment_intent_id: string, status: string, charge_id: string|null, amount_pence: int}
     */
    public function retrievePaymentIntent(string $connectedAccountId, string $paymentIntentId): array;

    /**
     * @return array{refund_id: string, status: string}
     */
    public function createRefund(string $connectedAccountId, string $paymentIntentId, int $amountPence, string $idempotencyKey): array;

  public function constructWebhookEvent(string $payload, string $signatureHeader): object;
}
