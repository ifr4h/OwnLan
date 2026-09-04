<?php

declare(strict_types=1);

namespace app\components;

use yii\web\NotFoundHttpException;

/**
 * Broad Companion product access gate.
 *
 * Underlying Companion architecture (models, services, tests) is preserved
 * but HTTP exposure is disabled for beta. See docs/17-companion-access-decision.md.
 *
 * Future narrow features (e.g. Payment Contact) may enable specific endpoints
 * without restoring the broad Companion product.
 */
final class CompanionFeature
{
    /** Whether broad Companion HTTP endpoints are exposed. */
    public const ENABLED = false;

    public static function requireEnabled(): void
    {
        if (!self::ENABLED) {
            throw new NotFoundHttpException('Not found.');
        }
    }
}
