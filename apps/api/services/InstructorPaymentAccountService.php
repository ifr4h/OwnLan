<?php

declare(strict_types=1);

namespace app\services;

use app\components\payments\PaymentPlatformFee;
use app\components\payments\StripeGatewayFactory;
use app\components\payments\StripeGatewayInterface;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\InstructorPaymentAccount;
use app\models\Organisation;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Stripe Connect Express account lifecycle for instructors.
 */
class InstructorPaymentAccountService
{
    private StripeGatewayInterface $stripe;

    public function __construct(?StripeGatewayInterface $stripe = null)
    {
        $this->stripe = $stripe ?? StripeGatewayFactory::make();
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $org = $this->requireOrganisation();
        $account = InstructorPaymentAccount::findOne(['organisation_id' => (int) $org->id]);

        return [
            'configured' => $this->stripe->isConfigured(),
            'account' => $account ? $this->serialize($account) : null,
            'fee_notice' => PaymentPlatformFee::feeDescription(),
            'stripe_publishable_key' => PaymentPlatformFee::stripePublishableKey(),
            'booking_payment_policy' => $org->booking_payment_policy ?? 'none',
            'booking_payment_options' => [
                ['value' => 'none', 'label' => 'Confirm the lesson without payment'],
                ['value' => 'request_after', 'label' => 'Ask for payment after booking'],
                ['value' => 'require_to_confirm', 'label' => 'Require payment to confirm'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function startOnboarding(): array
    {
        $org = $this->requireOrganisation();
        $instructor = Instructor::find()->andWhere(['organisation_id' => (int) $org->id])->orderBy(['id' => SORT_ASC])->one();
        if ($instructor === null) {
            throw new BadRequestHttpException('Instructor profile required.');
        }

        $account = InstructorPaymentAccount::findOne(['organisation_id' => (int) $org->id]);
        $now = gmdate('Y-m-d H:i:s');
        if ($account === null) {
            $account = new InstructorPaymentAccount();
            $account->organisation_id = (int) $org->id;
            $account->instructor_id = (int) $instructor->id;
            $account->provider = 'stripe';
            $account->status = InstructorPaymentAccount::STATUS_NOT_STARTED;
            $account->created_at = $now;
        }

        $base = rtrim((string) (getenv('WEB_URL') ?: 'http://127.0.0.1:3000'), '/');
        $returnUrl = $base . '/accounts/payments?onboarding=return';
        $refreshUrl = $base . '/accounts/payments?onboarding=refresh';

        if ($account->provider_account_id) {
            $state = $this->stripe->retrieveAccount((string) $account->provider_account_id);
            $this->applyProviderState($account, $state);
            $account->updated_at = $now;
            $account->save(false);

            if ($account->status !== InstructorPaymentAccount::STATUS_READY) {
                $link = $this->stripe->createAccountOnboardingLink(
                    (string) $account->provider_account_id,
                    $returnUrl,
                    $refreshUrl,
                );

                return [
                    'onboarding_url' => $link['onboarding_url'],
                    'account' => $this->serialize($account),
                ];
            }

            return ['account' => $this->serialize($account)];
        }

        $created = $this->stripe->createExpressAccount(
            (string) ($org->contact_email ?? 'instructor@example.com'),
            $returnUrl,
            $refreshUrl,
        );
        $account->provider_account_id = $created['account_id'];
        $account->status = InstructorPaymentAccount::STATUS_SETUP_INCOMPLETE;
        $account->updated_at = $now;
        $account->save(false);

        return [
            'onboarding_url' => $created['onboarding_url'],
            'account' => $this->serialize($account),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshStatus(): array
    {
        $org = $this->requireOrganisation();
        $account = InstructorPaymentAccount::findOne(['organisation_id' => (int) $org->id]);
        if ($account === null || !$account->provider_account_id) {
            return ['account' => null];
        }

        $state = $this->stripe->retrieveAccount((string) $account->provider_account_id);
        $this->applyProviderState($account, $state);
        $account->updated_at = gmdate('Y-m-d H:i:s');
        $account->save(false);

        return ['account' => $this->serialize($account)];
    }

    public function updateBookingPaymentPolicy(string $policy): array
    {
        $org = $this->requireOrganisation();
        if (!in_array($policy, ['none', 'request_after', 'require_to_confirm'], true)) {
            throw new BadRequestHttpException('Choose a valid booking payment policy.');
        }
        $org->booking_payment_policy = $policy;
        $org->updated_at = gmdate('Y-m-d H:i:s');
        $org->save(false, ['booking_payment_policy', 'updated_at']);

        return $this->settings();
    }

    /**
     * @param array<string, mixed> $state
     */
    private function applyProviderState(InstructorPaymentAccount $account, array $state): void
    {
        $account->charges_enabled = (bool) ($state['charges_enabled'] ?? false);
        $account->payouts_enabled = (bool) ($state['payouts_enabled'] ?? false);
        $account->details_submitted = (bool) ($state['details_submitted'] ?? false);
        $account->status = (string) ($state['status'] ?? InstructorPaymentAccount::STATUS_SETUP_INCOMPLETE);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(InstructorPaymentAccount $account): array
    {
        return [
            'status' => $account->status,
            'status_label' => $this->statusLabel($account->status),
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'details_submitted' => (bool) $account->details_submitted,
            'ready' => $account->status === InstructorPaymentAccount::STATUS_READY && $account->charges_enabled,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            InstructorPaymentAccount::STATUS_READY => 'Ready',
            InstructorPaymentAccount::STATUS_UNDER_REVIEW => 'Under review',
            InstructorPaymentAccount::STATUS_RESTRICTED => 'Payments restricted',
            InstructorPaymentAccount::STATUS_SETUP_INCOMPLETE => 'Setup incomplete',
            default => 'Not set up',
        };
    }

    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new BadRequestHttpException('Organisation not found.');
        }

        return $org;
    }
}
