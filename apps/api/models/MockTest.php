<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Instructor-conducted mock driving test (not an official DVSA test).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $instructor_id
 * @property int $learner_id
 * @property int|null $lesson_id
 * @property string $status in_progress|completed|abandoned
 * @property string|null $result pass_standard|not_pass_standard
 * @property string $started_at
 * @property string|null $finished_at
 * @property int $driving_faults_count
 * @property int $serious_faults_count
 * @property int $dangerous_faults_count
 * @property string|null $learner_summary
 * @property string|null $instructor_note
 * @property string|null $private_note
 * @property string|null $suggested_next_focus
 * @property string|null $client_session_id
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Learner $learner
 * @property-read Lesson|null $lesson
 * @property-read MockTestFault[] $faults
 */
class MockTest extends ActiveRecord
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';

    public const RESULT_PASS = 'pass_standard';
    public const RESULT_NOT_PASS = 'not_pass_standard';

    public static function tableName(): string
    {
        return '{{%mock_tests}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'instructor_id', 'learner_id', 'started_at', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'instructor_id', 'learner_id', 'lesson_id'], 'integer'],
            [['driving_faults_count', 'serious_faults_count', 'dangerous_faults_count'], 'integer', 'min' => 0],
            [['status'], 'in', 'range' => [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_ABANDONED]],
            [['result'], 'in', 'range' => [self::RESULT_PASS, self::RESULT_NOT_PASS]],
            [['learner_summary', 'instructor_note', 'private_note', 'suggested_next_focus'], 'string'],
            [['client_session_id'], 'string', 'max' => 64],
            [['started_at', 'finished_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function getLesson(): ActiveQuery
    {
        return $this->hasOne(Lesson::class, ['id' => 'lesson_id']);
    }

    public function getFaults(): ActiveQuery
    {
        return $this->hasMany(MockTestFault::class, ['mock_test_id' => 'id'])
            ->andWhere(['undone_at' => null])
            ->orderBy(['recorded_at' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }
}
