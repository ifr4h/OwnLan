<?php

declare(strict_types=1);

namespace app\services;

use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * One sign-in form — instructor or learner only.
 * Each person has their own account; no shared or proxy access.
 */
class UnifiedAuthService
{
    private AuthService $instructor;
    private PortalAuthService $portal;

    public function __construct(
        ?AuthService $instructor = null,
        ?PortalAuthService $portal = null,
    ) {
        $this->instructor = $instructor ?? new AuthService();
        $this->portal = $portal ?? new PortalAuthService();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function signIn(array $data): array
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if ($email === '' || $password === '') {
            throw new BadRequestHttpException('Email and password are required.');
        }

        if ($this->tryInstructor($data) !== null) {
            return [
                'account_type' => 'instructor',
                'redirect' => '/today',
                'payload' => $this->instructor->currentUserPayload(),
            ];
        }

        if ($this->tryLearner($data) !== null) {
            return [
                'account_type' => 'learner',
                'redirect' => '/portal',
                'payload' => $this->portal->currentPayload(),
            ];
        }

        throw new UnauthorizedHttpException('Email or password is incorrect.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tryInstructor(array $data): ?bool
    {
        try {
            $this->instructor->login($data);
        } catch (UnauthorizedHttpException) {
            return null;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tryLearner(array $data): ?bool
    {
        try {
            $this->portal->login($data);
        } catch (UnauthorizedHttpException) {
            return null;
        }

        return true;
    }
}
