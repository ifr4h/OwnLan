<?php

declare(strict_types=1);

namespace app\services;

use app\components\PortalContext;
use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerPortalAccount;
use app\models\Organisation;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\NotFoundHttpException;
use yii\web\TooManyRequestsHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Invite + activate + login for the learner portal.
 */
class PortalAuthService
{
    public const INVITE_TTL_DAYS = 14;

    public const INVITE_COOLDOWN_SECONDS = 60;

    private RateLimitService $rateLimit;
    private EmailDeliveryService $email;

    public function __construct(
        ?RateLimitService $rateLimit = null,
        ?EmailDeliveryService $email = null,
    ) {
        $this->rateLimit = $rateLimit ?? new RateLimitService();
        $this->email = $email ?? new EmailDeliveryService();
    }

    /**
     * Instructor invites a pupil to the learner portal.
     *
     * @return array<string, mixed>
     */
    public function inviteForLearner(int $learnerId): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var Organisation|null $org */
        $org = Organisation::findOne(['id' => $orgId]);
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }
        if ($learner->archived_at !== null) {
            throw new BadRequestHttpException('Archived pupils cannot use the portal.');
        }

        $email = mb_strtolower(trim((string) ($learner->email ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Add a pupil email before inviting them to the portal.');
        }

        $existingOther = LearnerPortalAccount::findByEmail($email);
        if ($existingOther !== null && (int) $existingOther->learner_id !== (int) $learner->id) {
            throw new ConflictHttpException('That email is already used by another portal account.');
        }

        /** @var LearnerPortalAccount|null $account */
        $account = LearnerPortalAccount::findOne(['learner_id' => (int) $learner->id]);
        if ($account !== null && $account->isActivated) {
            throw new BadRequestHttpException(
                $learner->first_name . ' is already connected. They can reset their password from the learner login page.',
            );
        }

        $userId = Yii::$app->user->isGuest ? 0 : (int) Yii::$app->user->id;
        $cooldownKey = "portal_invite:{$orgId}:{$learnerId}:{$userId}";
        $cooldownLeft = $this->rateLimit->secondsUntilReset($cooldownKey);
        if ($cooldownLeft > 0) {
            throw new TooManyRequestsHttpException('Invite sent a moment ago. Try again in a minute.');
        }

        $now = gmdate('Y-m-d H:i:s');
        if ($account === null) {
            $account = new LearnerPortalAccount();
            $account->organisation_id = $orgId;
            $account->learner_id = (int) $learner->id;
            $account->created_at = $now;
            $account->generateAuthKey();
        }

        $account->email = $email;
        $plainToken = Yii::$app->security->generateRandomString(48);
        $account->invite_token_hash = hash('sha256', $plainToken);
        $account->invite_expires_at = gmdate('Y-m-d H:i:s', time() + self::INVITE_TTL_DAYS * 86400);
        $account->invite_sent_at = $now;
        $account->last_invite_at = $now;
        $account->updated_at = $now;

        if (!$account->save()) {
            throw new BadRequestHttpException($this->firstError($account));
        }

        $this->rateLimit->hit(
            $cooldownKey,
            1,
            self::INVITE_COOLDOWN_SECONDS,
            'Invite sent a moment ago. Try again in a minute.',
        );

        $invitePath = '/portal/join?token=' . urlencode($plainToken);
        $delivery = $this->email->canDeliver()
            ? $this->sendInviteEmail($learner, $org, $plainToken)
            : ['sent' => false, 'mode' => 'skipped', 'error' => null];

        $deliveryMode = $delivery['sent'] ? 'email' : 'link';
        $message = $delivery['sent']
            ? 'Invite email sent to ' . $email . '.'
            : 'Invite link ready — copy it for ' . $learner->first_name . '.';

        return [
            'learner_id' => (int) $learner->id,
            'learner_first_name' => $learner->first_name,
            'email' => $account->email,
            'activated' => false,
            'delivery_mode' => $deliveryMode,
            'email_sent' => $delivery['sent'],
            'email_error' => $delivery['error'],
            'invite_path' => $invitePath,
            'invite_expires_at' => $account->invite_expires_at,
            'invite_expires_label' => self::INVITE_TTL_DAYS . ' days',
            'message' => $message,
            'can_copy_link' => true,
        ];
    }

    /**
     * Peek invite (public) — first name only, no sensitive data.
     *
     * @return array<string, mixed>
     */
    public function peekInvite(string $token): array
    {
        $account = $this->findByInviteToken($token, allowExpired: true);
        $learner = $account->learner;
        if ($learner === null || $learner->archived_at !== null) {
            throw new NotFoundHttpException('This invite is no longer valid.');
        }

        if ($account->isActivated) {
            return [
                'state' => 'already_connected',
                'learner_first_name' => $learner->first_name,
            ];
        }

        if ($account->invite_expires_at !== null && $account->invite_expires_at < gmdate('Y-m-d H:i:s')) {
            return [
                'state' => 'expired',
                'learner_first_name' => $learner->first_name,
            ];
        }

        return [
            'state' => 'valid',
            'learner_first_name' => $learner->first_name,
            'email' => $account->email,
            'already_activated' => false,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function activate(array $data): array
    {
        $token = trim((string) ($data['token'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($token === '') {
            throw new BadRequestHttpException('Invite token is required.');
        }
        if (mb_strlen($password) < 8) {
            throw new BadRequestHttpException('Password must be at least 8 characters.');
        }

        $account = $this->findByInviteToken($token, allowExpired: false);
        $learner = $account->learner;
        if ($learner === null || $learner->archived_at !== null) {
            throw new BadRequestHttpException('This invite is no longer valid.');
        }
        if ($account->isActivated) {
            throw new BadRequestHttpException(
                'This account is already set up. Use forgot password if you need to sign in.',
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $account->setPassword($password);
        $account->generateAuthKey();
        $account->activated_at = $now;
        $account->invite_token_hash = null;
        $account->invite_expires_at = null;
        $account->updated_at = $now;
        if (!$account->save()) {
            throw new BadRequestHttpException($this->firstError($account));
        }

        $this->loginAccount($account);

        return $this->currentPayload();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function login(array $data): array
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if ($email === '' || $password === '') {
            throw new BadRequestHttpException('Email and password are required.');
        }

        $account = LearnerPortalAccount::findByEmail($email);
        if ($account === null || !$account->isActivated || !$account->validatePassword($password)) {
            throw new UnauthorizedHttpException('Email or password is incorrect.');
        }

        $learner = $account->learner;
        if ($learner === null || $learner->archived_at !== null) {
            throw new UnauthorizedHttpException('This portal account is no longer available.');
        }

        $this->loginAccount($account);

        return $this->currentPayload();
    }

    public function logout(): void
    {
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function currentPayload(): array
    {
        $account = PortalContext::requireAccount();
        $learner = $account->learner;
        if ($learner === null) {
            throw new UnauthorizedHttpException('Learner not found.');
        }

        return [
            'account' => [
                'id' => (int) $account->id,
                'email' => $account->email,
            ],
            'learner' => [
                'id' => (int) $learner->id,
                'first_name' => $learner->first_name,
                'full_name' => $learner->fullName,
            ],
        ];
    }

    /**
     * Portal status for instructor pupil view.
     *
     * @return array<string, mixed>
     */
    public function statusForLearner(int $learnerId): array
    {
        TenantContext::requireOrganisationId();
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        $hasEmail = $learner->email !== null && trim((string) $learner->email) !== '';

        /** @var LearnerPortalAccount|null $account */
        $account = LearnerPortalAccount::findOne(['learner_id' => $learnerId]);
        if ($account === null) {
            return [
                'status' => 'not_invited',
                'status_label' => 'Not invited',
                'email' => $learner->email,
                'portal_email' => null,
                'activated' => false,
                'can_invite' => $hasEmail,
                'delivery_available' => $this->email->canDeliver(),
            ];
        }

        if ($account->isActivated) {
            return [
                'status' => 'connected',
                'status_label' => 'Connected',
                'email' => $account->email,
                'portal_email' => $account->email,
                'activated' => true,
                'can_invite' => false,
                'connected_since' => $account->activated_at,
                'connected_since_display' => $this->formatConnectedSince($account->activated_at),
                'delivery_available' => $this->email->canDeliver(),
            ];
        }

        $expired = $account->invite_expires_at !== null
            && $account->invite_expires_at < gmdate('Y-m-d H:i:s');
        $pending = $account->invite_token_hash !== null && !$expired;

        return [
            'status' => $expired ? 'invite_expired' : 'invite_pending',
            'status_label' => $expired ? 'Invite expired' : 'Invite pending',
            'email' => $account->email,
            'portal_email' => $account->email,
            'activated' => false,
            'can_invite' => true,
            'invite_pending' => $pending,
            'invite_expired' => $expired,
            'invite_expires_at' => $account->invite_expires_at,
            'last_invite_at' => $account->last_invite_at,
            'delivery_available' => $this->email->canDeliver(),
        ];
    }

    private function loginAccount(LearnerPortalAccount $account): void
    {
        if (!Yii::$app->user->isGuest) {
            Yii::$app->user->logout(false);
            TenantContext::clear();
        }
        if (Yii::$app->has('companionUser')) {
            Yii::$app->companionUser->logout();
        }
        Yii::$app->portalUser->login($account, 0);
    }

    private function findByInviteToken(string $token, bool $allowExpired): LearnerPortalAccount
    {
        $token = trim($token);
        if ($token === '') {
            throw new BadRequestHttpException('Invite token is required.');
        }
        $hash = hash('sha256', $token);
        /** @var LearnerPortalAccount|null $account */
        $account = LearnerPortalAccount::find()
            ->andWhere(['invite_token_hash' => $hash])
            ->one();
        if ($account === null) {
            throw new NotFoundHttpException('This invite link is invalid or has already been used.');
        }
        if (!$allowExpired && $account->invite_expires_at !== null && $account->invite_expires_at < gmdate('Y-m-d H:i:s')) {
            throw new BadRequestHttpException('This invite link has expired. Ask your instructor for a new one.');
        }

        return $account;
    }

    /**
     * @return array{sent: bool, mode: string, error: string|null}
     */
    private function sendInviteEmail(Learner $learner, ?Organisation $org, string $plainToken): array
    {
        $url = $this->frontendUrl('/portal/join?token=' . urlencode($plainToken));
        $business = $org?->name ?? 'Your instructor';
        $subject = 'Your OwnLane invite';
        $text = $learner->first_name . ",\n\n"
            . $business . " has invited you to OwnLane.\n\n"
            . "You can use OwnLane to see your upcoming lessons, lesson recaps, progress and resources shared by your instructor.\n\n"
            . "Set up your account: {$url}\n\n"
            . 'This link expires in ' . self::INVITE_TTL_DAYS . " days.\n";

        return $this->email->send($learner->email ?? '', $subject, $text);
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) (getenv('FRONTEND_URL') ?: 'http://localhost:3000'), '/');

        return $base . $path;
    }

    private function formatConnectedSince(?string $activatedAt): ?string
    {
        if ($activatedAt === null || $activatedAt === '') {
            return null;
        }
        $ts = strtotime($activatedAt . ' UTC');
        if ($ts === false) {
            return null;
        }

        return gmdate('j M Y', $ts);
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors !== [] ? (string) reset($errors) : 'Could not save.';
    }
}
