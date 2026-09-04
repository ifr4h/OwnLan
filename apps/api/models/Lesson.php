<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $instructor_id
 * @property int $learner_id
 * @property int|null $series_id
 * @property string $starts_at UTC datetime Y-m-d H:i:s
 * @property int $duration_minutes
 * @property string|null $pickup_address
 * @property string $status
 * @property string|null $instructor_notes
 * @property string|null $learner_summary
 * @property string|null $next_focus
 * @property string|null $client_mutation_id
 * @property string|null $cancelled_at
 * @property string|null $completed_at
 * @property string|null $no_show_at
 * @property int|null $price_pence
 * @property string|null $settlement package|outstanding|paid|waived — money/credit outcome, not lesson status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Organisation $organisation
 * @property-read Instructor $instructor
 * @property-read Learner $learner
 * @property-read LessonSeries|null $series
 */
class Lesson extends ActiveRecord
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const DEFAULT_DURATION_MINUTES = 60;

    public static function tableName(): string
    {
        return '{{%lessons}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'instructor_id',
                'learner_id',
                'starts_at',
                'duration_minutes',
                'status',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'instructor_id', 'learner_id', 'series_id', 'duration_minutes', 'price_pence'], 'integer'],
            [['duration_minutes'], 'integer', 'min' => 15, 'max' => 480],
            [['price_pence'], 'integer', 'min' => 0],
            [['pickup_address', 'instructor_notes', 'learner_summary', 'next_focus'], 'string'],
            [['client_mutation_id'], 'string', 'max' => 64],
            [['settlement'], 'string', 'max' => 32],
            [['status'], 'in', 'range' => [
                self::STATUS_SCHEDULED,
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED,
                self::STATUS_NO_SHOW,
            ]],
            [['starts_at', 'cancelled_at', 'completed_at', 'no_show_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function getInstructor(): ActiveQuery
    {
        return $this->hasOne(Instructor::class, ['id' => 'instructor_id']);
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function getSeries(): ActiveQuery
    {
        return $this->hasOne(LessonSeries::class, ['id' => 'series_id']);
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }
}
