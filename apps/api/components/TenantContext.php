<?php

declare(strict_types=1);

namespace app\components;

use app\models\Membership;
use app\models\User;
use Yii;
use yii\db\ActiveQuery;
use yii\web\ForbiddenHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Resolves and enforces the active organisation for the logged-in user.
 * Every business query must go through organisation scoping.
 */
final class TenantContext
{
    private const SESSION_ORG_KEY = 'active_organisation_id';

    public static function bootstrapFromUser(User $user): void
    {
        $membership = Membership::find()
            ->where(['user_id' => (int) $user->id])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        if ($membership === null) {
            self::clear();
            return;
        }

        Yii::$app->session->set(self::SESSION_ORG_KEY, (int) $membership->organisation_id);
    }

    public static function organisationId(): ?int
    {
        if (Yii::$app->user->isGuest) {
            return null;
        }

        $fromSession = Yii::$app->session->get(self::SESSION_ORG_KEY);
        if ($fromSession !== null) {
            return (int) $fromSession;
        }

        /** @var User|null $user */
        $user = Yii::$app->user->identity;
        if ($user === null) {
            return null;
        }

        self::bootstrapFromUser($user);

        $fromSession = Yii::$app->session->get(self::SESSION_ORG_KEY);

        return $fromSession !== null ? (int) $fromSession : null;
    }

    /**
     * @throws UnauthorizedHttpException
     * @throws ForbiddenHttpException
     */
    public static function requireOrganisationId(): int
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }

        $organisationId = self::organisationId();
        if ($organisationId === null) {
            throw new ForbiddenHttpException('No active organisation.');
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        $allowed = Membership::find()
            ->where([
                'user_id' => (int) $user->id,
                'organisation_id' => $organisationId,
            ])
            ->exists();

        if (!$allowed) {
            self::clear();
            throw new ForbiddenHttpException('You do not have access to this organisation.');
        }

        return $organisationId;
    }

    /**
     * Apply organisation_id filtering to a query. Never fetch by id alone.
     */
    public static function scopeByOrganisation(ActiveQuery $query, string $column = 'organisation_id'): ActiveQuery
    {
        return $query->andWhere([$column => self::requireOrganisationId()]);
    }

    /**
     * True when the given organisation is the caller's active tenant.
     */
    public static function ownsOrganisation(int $organisationId): bool
    {
        try {
            return self::requireOrganisationId() === $organisationId;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function clear(): void
    {
        if (Yii::$app->has('session', true)) {
            Yii::$app->session->remove(self::SESSION_ORG_KEY);
        }
    }
}
