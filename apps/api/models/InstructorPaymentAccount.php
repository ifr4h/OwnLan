<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int|null $instructor_id
 * @property string $provider
 * @property string|null $provider_account_id
 * @property string $status
 * @property bool $charges_enabled
 * @property bool $payouts_enabled
 * @property bool $details_submitted
 * @property string $created_at
 * @property string $updated_at
 */
class InstructorPaymentAccount extends ActiveRecord
{
    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_SETUP_INCOMPLETE = 'setup_incomplete';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_READY = 'ready';
    public const STATUS_RESTRICTED = 'restricted';

    public static function tableName(): string
    {
        return '{{%instructor_payment_accounts}}';
    }
}
