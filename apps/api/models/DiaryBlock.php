<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Instructor-only diary block (no pupil). Never shown in the learner portal.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $instructor_id
 * @property string $starts_at UTC
 * @property int $duration_minutes
 * @property string $label
 * @property string $kind
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Instructor $instructor
 */
class DiaryBlock extends ActiveRecord
{
    public const KIND_PRIVATE = 'private';

    public static function tableName(): string
    {
        return '{{%diary_blocks}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'instructor_id',
                'starts_at',
                'duration_minutes',
                'label',
                'kind',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'instructor_id', 'duration_minutes'], 'integer'],
            [['duration_minutes'], 'integer', 'min' => 15, 'max' => 12 * 60],
            [['label'], 'string', 'max' => 120],
            [['kind'], 'string', 'max' => 32],
            [['starts_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getInstructor(): ActiveQuery
    {
        return $this->hasOne(Instructor::class, ['id' => 'instructor_id']);
    }
}
