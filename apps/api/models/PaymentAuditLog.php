<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int|null $payment_id
 * @property int|null $checkout_id
 * @property string $action
 * @property int|null $actor_user_id
 * @property string|null $meta_json
 * @property string $created_at
 */
class PaymentAuditLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_audit_log}}';
    }
}
