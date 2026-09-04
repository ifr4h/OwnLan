<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $event_id
 * @property string $event_type
 * @property string $processed_at
 * @property int|null $payment_checkout_id
 */
class StripeWebhookEvent extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%stripe_webhook_events}}';
    }
}
