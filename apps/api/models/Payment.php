<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Money received (or voided). Distinct from package purchase and lesson completion.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int $amount_pence
 * @property string $method
 * @property string $purpose
 * @property string $recorded_at
 * @property string|null $notes
 * @property int|null $lesson_id
 * @property int|null $package_id
 * @property bool $counts_as_income
 * @property int|null $created_by_user_id
 * @property string|null $voided_at
 * @property string|null $void_reason
 * @property string|null $provider
 * @property string|null $provider_payment_id
 * @property string|null $provider_charge_id
 * @property string|null $provider_status
 * @property string $payment_status
 * @property int|null $platform_fee_pence
 * @property int|null $processor_fee_pence
 * @property int|null $net_pence
 * @property string|null $refund_provider_id
 * @property string|null $refunded_at
 * @property string|null $idempotency_key
 * @property string $created_at
 * @property string $updated_at
 */
class Payment extends ActiveRecord
{
    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CARD = 'card';
    public const METHOD_OTHER = 'other';

    public const PROVIDER_STRIPE = 'stripe';
    public const PROVIDER_MANUAL = 'manual';

    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_DISPUTED = 'disputed';

    public const PURPOSE_LESSON_BALANCE = 'lesson_balance';
    public const PURPOSE_PACKAGE = 'package';

    public static function tableName(): string
    {
        return '{{%payments}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'learner_id',
                'amount_pence',
                'method',
                'purpose',
                'recorded_at',
                'created_at',
                'updated_at',
            ], 'required'],
            [[
                'organisation_id',
                'learner_id',
                'amount_pence',
                'lesson_id',
                'package_id',
                'created_by_user_id',
            ], 'integer'],
            [['counts_as_income'], 'boolean'],
            [['notes', 'void_reason'], 'string'],
            [['method'], 'in', 'range' => [
                self::METHOD_CASH,
                self::METHOD_BANK_TRANSFER,
                self::METHOD_CARD,
                self::METHOD_OTHER,
            ]],
            [['purpose'], 'in', 'range' => [
                self::PURPOSE_LESSON_BALANCE,
                self::PURPOSE_PACKAGE,
            ]],
            [['recorded_at', 'voided_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getIsVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }
}
