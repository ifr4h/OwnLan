<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Explicitly recorded GPS route for a lesson (never silent tracking).
 *
 * Status: recording | completed | discarded
 * Geometry stored as Google-encoded polyline — lean, no GIS extension required.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $lesson_id
 * @property int $learner_id
 * @property string $status
 * @property string|null $started_at
 * @property string|null $ended_at
 * @property int|null $duration_seconds
 * @property int|null $distance_metres
 * @property int $point_count
 * @property string|null $encoded_polyline
 * @property string|null $bounds_json
 * @property string|null $label
 * @property bool $learner_visible
 * @property string|null $shared_at
 * @property string|null $deleted_at
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Lesson $lesson
 * @property-read Learner $learner
 */
class LessonRoute extends ActiveRecord
{
    public const STATUS_RECORDING = 'recording';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DISCARDED = 'discarded';

    public static function tableName(): string
    {
        return '{{%lesson_routes}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'lesson_id', 'learner_id', 'status', 'created_at', 'updated_at'], 'required'],
            [[
                'organisation_id',
                'lesson_id',
                'learner_id',
                'duration_seconds',
                'distance_metres',
                'point_count',
            ], 'integer'],
            [['learner_visible'], 'boolean'],
            [['encoded_polyline', 'bounds_json', 'label'], 'string'],
            [['status'], 'in', 'range' => [
                self::STATUS_RECORDING,
                self::STATUS_COMPLETED,
                self::STATUS_DISCARDED,
            ]],
            [[
                'started_at',
                'ended_at',
                'shared_at',
                'deleted_at',
                'created_at',
                'updated_at',
            ], 'safe'],
        ];
    }

    public function getLesson(): ActiveQuery
    {
        return $this->hasOne(Lesson::class, ['id' => 'lesson_id']);
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function isActiveRecording(): bool
    {
        return $this->status === self::STATUS_RECORDING && $this->deleted_at === null;
    }
}
