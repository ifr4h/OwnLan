<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Money owed for a completed lesson not covered by package credit.
 * Distinct from payment received and from accounting income.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int $lesson_id
 * @property int $amount_pence
 * @property int $amount_paid_pence
 * @property string $status
 * @property string|null $voided_at
 * @property string|null $void_reason
 * @property string $created_at
 * @property string $updated_at
 */
class LessonCharge extends ActiveRecord
{
    public const STATUS_OUTSTANDING = 'outstanding';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOIDED = 'voided';

    public static function tableName(): string
    {
        return '{{%lesson_charges}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'learner_id',
                'lesson_id',
                'amount_pence',
                'amount_paid_pence',
                'status',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'learner_id', 'lesson_id', 'amount_pence', 'amount_paid_pence'], 'integer'],
            [['void_reason'], 'string'],
            [['status'], 'in', 'range' => [
                self::STATUS_OUTSTANDING,
                self::STATUS_PAID,
                self::STATUS_VOIDED,
            ]],
            [['voided_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function outstandingPence(): int
    {
        if ($this->status === self::STATUS_VOIDED) {
            return 0;
        }

        return max(0, (int) $this->amount_pence - (int) $this->amount_paid_pence);
    }

    public function getLesson(): ActiveQuery
    {
        return $this->hasOne(Lesson::class, ['id' => 'lesson_id']);
    }
}
