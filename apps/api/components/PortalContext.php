<?php

declare(strict_types=1);

namespace app\components;

use app\models\LearnerPortalAccount;
use Yii;
use yii\db\ActiveQuery;
use yii\web\UnauthorizedHttpException;

/**
 * Learner-portal request context — never uses instructor TenantContext.
 */
final class PortalContext
{
    public static function account(): ?LearnerPortalAccount
    {
        if (!Yii::$app->has('portalUser')) {
            return null;
        }

        /** @var LearnerPortalAccount|null $identity */
        $identity = Yii::$app->portalUser->identity;

        return $identity instanceof LearnerPortalAccount ? $identity : null;
    }

    /**
     * @throws UnauthorizedHttpException
     */
    public static function requireAccount(): LearnerPortalAccount
    {
        $account = self::account();
        if ($account === null || !$account->isActivated) {
            throw new UnauthorizedHttpException('Learner portal authentication required.');
        }

        return $account;
    }

    /**
     * Scope a query to the authenticated learner only (org + learner).
     *
     * @throws UnauthorizedHttpException
     */
    public static function scopeOwnLearner(
        ActiveQuery $query,
        string $learnerColumn = 'learner_id',
        string $organisationColumn = 'organisation_id',
    ): ActiveQuery {
        $account = self::requireAccount();

        return $query->andWhere([
            $learnerColumn => (int) $account->learner_id,
            $organisationColumn => (int) $account->organisation_id,
        ]);
    }
}
