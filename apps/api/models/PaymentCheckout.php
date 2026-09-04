<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string $token
 * @property string $purpose
 * @property int $amount_pence
 * @property string $currency
 * @property int|null $lesson_charge_id
 * @property int|null $package_offering_id
 * @property int|null $booking_hold_id
 * @property int|null $payment_id
 * @property string|null $provider_payment_intent_id
 * @property string|null $provider_checkout_session_id
 * @property string $status
 * @property string $idempotency_key
 * @property string|null $expires_at
 * @property string $created_at
 * @property string $updated_at
 */
class PaymentCheckout extends ActiveRecord
{
    public const PURPOSE_OUTSTANDING = 'outstanding_balance';
    public const PURPOSE_PACKAGE = 'package_offering';
    public const PURPOSE_BOOKING_HOLD = 'booking_hold';
    public const PURPOSE_PAYMENT_REQUEST = 'payment_request';

    public const STATUS_CREATED = 'created';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public static function tableName(): string
    {
        return '{{%payment_checkouts}}';
    }
}
