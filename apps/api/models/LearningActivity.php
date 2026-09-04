<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Learner learning-app activity — never mutates instructor assessments.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int|null $content_id
 * @property string|null $content_slug
 * @property string $activity_type
 * @property string|null $result_json
 * @property string $created_at
 */
class LearningActivity extends ActiveRecord
{
    public const TYPE_OPENED = 'opened';
    public const TYPE_COMPLETED = 'completed';
    public const TYPE_SCENARIO = 'scenario_completed';
    public const TYPE_QUICK_REVIEW = 'quick_review';

    public static function tableName(): string
    {
        return '{{%learning_activities}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'activity_type', 'created_at'], 'required'],
            [['organisation_id', 'learner_id', 'content_id'], 'integer'],
            [['content_slug'], 'string', 'max' => 120],
            [['activity_type'], 'string', 'max' => 32],
            [['result_json'], 'string'],
            [['created_at'], 'safe'],
        ];
    }
}
