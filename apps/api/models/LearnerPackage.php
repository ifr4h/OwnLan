<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Purchased lesson hours/credits for a pupil.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string|null $label
 * @property int $purchased_minutes
 * @property int $remaining_minutes
 * @property int $price_pence
 * @property int|null $payment_id
 * @property string $purchased_at
 * @property string|null $notes
 * @property string $status
 * @property string|null $voided_at
 * @property string|null $void_reason
 * @property string $created_at
 * @property string $updated_at
 */
class LearnerPackage extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_VOIDED = 'voided';

    public static function tableName(): string
    {
        return '{{%learner_packages}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'learner_id',
                'purchased_minutes',
                'remaining_minutes',
                'price_pence',
                'purchased_at',
                'status',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'learner_id', 'purchased_minutes', 'remaining_minutes', 'price_pence', 'payment_id'], 'integer'],
            [['label'], 'string', 'max' => 120],
            [['notes', 'void_reason'], 'string'],
            [['status'], 'in', 'range' => [
                self::STATUS_ACTIVE,
                self::STATUS_EXHAUSTED,
                self::STATUS_VOIDED,
            ]],
            [['purchased_at', 'voided_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function getPayment(): ActiveQuery
    {
        return $this->hasOne(Payment::class, ['id' => 'payment_id']);
    }
}
