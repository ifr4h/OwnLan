<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $instructor_id
 * @property int $learner_id
 * @property string $starts_at
 * @property int $duration_minutes
 * @property int $price_pence
 * @property string|null $pickup_address
 * @property int|null $payment_checkout_id
 * @property int|null $lesson_id
 * @property string $status
 * @property string $expires_at
 * @property string $created_at
 * @property string $updated_at
 */
class BookingHold extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CANCELLED = 'cancelled';

    public static function tableName(): string
    {
        return '{{%booking_holds}}';
    }
}
