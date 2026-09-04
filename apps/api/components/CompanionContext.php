<?php

declare(strict_types=1);

namespace app\components;

use app\models\CompanionAccount;
use app\models\LearnerCompanion;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Companion request context — separate from learner portal and instructor.
 */
final class CompanionContext
{
    public static function account(): ?CompanionAccount
    {
        if (!Yii::$app->has('companionUser')) {
            return null;
        }
        $identity = Yii::$app->companionUser->identity;

        return $identity instanceof CompanionAccount ? $identity : null;
    }

    public static function requireAccount(): CompanionAccount
    {
        $account = self::account();
        if ($account === null || !$account->isActivated) {
            throw new UnauthorizedHttpException('Companion authentication required.');
        }

        return $account;
    }

    /**
     * Active link for a learner this companion may access.
     *
     * @throws ForbiddenHttpException
     */
    public static function requireLink(int $learnerId): LearnerCompanion
    {
        $account = self::requireAccount();
        $link = LearnerCompanion::findOne([
            'companion_account_id' => (int) $account->id,
            'learner_id' => $learnerId,
            'revoked_at' => null,
        ]);
        if ($link === null) {
            throw new ForbiddenHttpException('You do not have access to this learner.');
        }

        return $link;
    }

    /**
     * @throws ForbiddenHttpException
     */
    public static function requirePermission(int $learnerId, string $permission): LearnerCompanion
    {
        $link = self::requireLink($learnerId);
        if (!$link->can($permission)) {
            throw new ForbiddenHttpException('This access is not allowed.');
        }

        return $link;
    }
}
