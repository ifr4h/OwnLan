<?php

declare(strict_types=1);

namespace app\services;

use app\models\LearnerPortalAccount;
use app\models\PasswordResetToken;
use app\models\User;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Password recovery for instructor User accounts and learner portal accounts.
 *
 * Systems stay separate — the same email may exist in both without cross-triggering.
 */
class PasswordResetService
{
    public const RESET_TTL_SECONDS = 3600;

    public const GENERIC_REQUEST_MESSAGE = 'If there\'s an OwnLane account for that address, you\'ll receive a reset link.';

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
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function requestInstructor(array $data): array
    {
        $email = $this->normalizeEmail($data['email'] ?? '');
        $this->throttleResetRequest(PasswordResetToken::TYPE_INSTRUCTOR, $email);

        $user = User::findByEmail($email);
        if ($user !== null) {
            $plain = $this->issueToken(PasswordResetToken::TYPE_INSTRUCTOR, (int) $user->id);
            $this->sendInstructorResetEmail($user, $plain);
        }

        return $this->requestResponse();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function requestPortal(array $data): array
    {
        $email = $this->normalizeEmail($data['email'] ?? '');
        $this->throttleResetRequest(PasswordResetToken::TYPE_PORTAL, $email);

        $account = LearnerPortalAccount::findByEmail($email);
        if ($account !== null && $account->isActivated) {
            $plain = $this->issueToken(PasswordResetToken::TYPE_PORTAL, (int) $account->id);
            $this->sendPortalResetEmail($account, $plain);
        }

        return $this->requestResponse();
    }

    /**
     * @return array<string, mixed>
     */
    public function peek(string $token, string $accountType): array
    {
        $row = $this->findTokenRow($token, $accountType);
        if ($row === null) {
            return ['state' => 'invalid'];
        }
        if ($row->isUsed()) {
            return ['state' => 'used'];
        }
        if ($row->isExpired()) {
            return ['state' => 'expired'];
        }

        return ['state' => 'valid'];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function confirm(string $accountType, array $data): array
    {
        $token = trim((string) ($data['token'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($token === '') {
            throw new BadRequestHttpException('Reset token is required.');
        }
        if (mb_strlen($password) < 8) {
            throw new BadRequestHttpException('Password must be at least 8 characters.');
        }

        $row = $this->findTokenRow($token, $accountType);
        if ($row === null) {
            throw new NotFoundHttpException('This reset link is invalid.');
        }
        if ($row->isUsed()) {
            throw new BadRequestHttpException('This reset link has already been used.');
        }
        if ($row->isExpired()) {
            throw new BadRequestHttpException('This reset link has expired.');
        }

        $now = gmdate('Y-m-d H:i:s');
        if ($accountType === PasswordResetToken::TYPE_INSTRUCTOR) {
            $user = User::findOne(['id' => (int) $row->account_id]);
            if ($user === null) {
                throw new NotFoundHttpException('This reset link is invalid.');
            }
            $user->setPassword($password);
            $user->generateAuthKey();
            $user->updated_at = $now;
            if (!$user->save(true, ['password_hash', 'auth_key', 'updated_at'])) {
                throw new BadRequestHttpException('Could not update password.');
            }
        } elseif ($accountType === PasswordResetToken::TYPE_PORTAL) {
            $account = LearnerPortalAccount::findOne(['id' => (int) $row->account_id]);
            if ($account === null || !$account->isActivated) {
                throw new NotFoundHttpException('This reset link is invalid.');
            }
            $account->setPassword($password);
            $account->generateAuthKey();
            $account->updated_at = $now;
            if (!$account->save(true, ['password_hash', 'auth_key', 'updated_at'])) {
                throw new BadRequestHttpException('Could not update password.');
            }
        } else {
            throw new BadRequestHttpException('Invalid account type.');
        }

        $row->used_at = $now;
        $row->save(false, ['used_at']);
        $this->invalidateUnusedTokens($accountType, (int) $row->account_id, (int) $row->id);

        return [
            'status' => 'ok',
            'message' => 'Password updated. You can sign in with your new password.',
        ];
    }

    private function issueToken(string $accountType, int $accountId): string
    {
        $this->invalidateUnusedTokens($accountType, $accountId, null);

        $plain = Yii::$app->security->generateRandomString(48);
        $now = gmdate('Y-m-d H:i:s');
        $row = new PasswordResetToken();
        $row->account_type = $accountType;
        $row->account_id = $accountId;
        $row->token_hash = hash('sha256', $plain);
        $row->expires_at = gmdate('Y-m-d H:i:s', time() + self::RESET_TTL_SECONDS);
        $row->created_at = $now;
        if (!$row->save()) {
            throw new BadRequestHttpException('Could not create reset token.');
        }

        return $plain;
    }

    private function invalidateUnusedTokens(string $accountType, int $accountId, ?int $exceptId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $query = PasswordResetToken::find()
            ->andWhere([
                'account_type' => $accountType,
                'account_id' => $accountId,
            ])
            ->andWhere(['used_at' => null]);
        if ($exceptId !== null) {
            $query->andWhere(['not', ['id' => $exceptId]]);
        }
        /** @var PasswordResetToken[] $rows */
        $rows = $query->all();
        foreach ($rows as $row) {
            $row->used_at = $now;
            $row->save(false, ['used_at']);
        }
    }

    private function findTokenRow(string $token, string $accountType): ?PasswordResetToken
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        if (!in_array($accountType, [PasswordResetToken::TYPE_INSTRUCTOR, PasswordResetToken::TYPE_PORTAL], true)) {
            return null;
        }

        /** @var PasswordResetToken|null $row */
        $row = PasswordResetToken::find()
            ->andWhere([
                'token_hash' => hash('sha256', $token),
                'account_type' => $accountType,
            ])
            ->one();

        return $row;
    }

    private function throttleResetRequest(string $accountType, string $email): void
    {
        $ip = Yii::$app->request->userIP ?? 'unknown';
        $this->rateLimit->hit(
            "pw_reset:{$accountType}:ip:{$ip}",
            10,
            3600,
            'Too many reset requests. Try again later.',
        );
        if ($email !== '') {
            $this->rateLimit->hit(
                'pw_reset:' . $accountType . ':email:' . hash('sha256', $email),
                5,
                3600,
                'Too many reset requests for this email. Try again later.',
            );
        }
    }

    /**
     * @return array{message: string, headline: string}
     */
    private function requestResponse(): array
    {
        return [
            'headline' => 'Check your email',
            'message' => self::GENERIC_REQUEST_MESSAGE,
        ];
    }

    private function normalizeEmail(mixed $value): string
    {
        $email = mb_strtolower(trim((string) $value));
        if ($email === '') {
            throw new BadRequestHttpException('Email is required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Enter a valid email address.');
        }

        return $email;
    }

    private function sendInstructorResetEmail(User $user, string $plainToken): void
    {
        $url = $this->frontendUrl('/reset-password?token=' . urlencode($plainToken));
        $subject = 'Reset your OwnLane password';
        $text = "Hi {$user->name},\n\n"
            . "We received a request to reset your OwnLane password.\n\n"
            . "Reset your password: {$url}\n\n"
            . "This link expires in 1 hour. If you did not ask for this, you can ignore this email.\n";
        $this->email->send($user->email, $subject, $text);
    }

    private function sendPortalResetEmail(LearnerPortalAccount $account, string $plainToken): void
    {
        $url = $this->frontendUrl('/portal/reset-password?token=' . urlencode($plainToken));
        $first = $account->learner?->first_name ?? 'there';
        $subject = 'Reset your OwnLane password';
        $text = "Hi {$first},\n\n"
            . "We received a request to reset your learner portal password.\n\n"
            . "Reset your password: {$url}\n\n"
            . "This link expires in 1 hour. If you did not ask for this, you can ignore this email.\n";
        $this->email->send($account->email, $subject, $text);
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) (getenv('FRONTEND_URL') ?: 'http://localhost:3000'), '/');

        return $base . $path;
    }
}
