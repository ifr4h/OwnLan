<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Weekly lesson series metadata (occurrences live in lessons.series_id).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $instructor_id
 * @property int $learner_id
 * @property int $duration_minutes
 * @property string|null $pickup_address
 * @property string $anchor_starts_at_local
 * @property string|null $until_date
 * @property int|null $occurrence_count
 * @property string $created_at
 * @property string $updated_at
 */
class LessonSeries extends ActiveRecord
{
    public const MAX_OCCURRENCES = 52;

    public static function tableName(): string
    {
        return '{{%lesson_series}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'instructor_id',
                'learner_id',
                'duration_minutes',
                'anchor_starts_at_local',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'instructor_id', 'learner_id', 'duration_minutes', 'occurrence_count'], 'integer'],
            [['duration_minutes'], 'integer', 'min' => 15, 'max' => 480],
            [['occurrence_count'], 'integer', 'min' => 2, 'max' => self::MAX_OCCURRENCES],
            [['pickup_address'], 'string'],
            [['anchor_starts_at_local'], 'string', 'max' => 32],
            [['until_date', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getLessons(): ActiveQuery
    {
        return $this->hasMany(Lesson::class, ['series_id' => 'id']);
    }
}
