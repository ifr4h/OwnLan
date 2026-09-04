<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Skills practised / focused on during a lesson.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $lesson_id
 * @property int $skill_id
 * @property string $created_at
 *
 * @property-read ProgressSkill $skill
 * @property-read Lesson $lesson
 */
class LessonSkill extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%lesson_skills}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'lesson_id', 'skill_id', 'created_at'], 'required'],
            [['organisation_id', 'lesson_id', 'skill_id'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    public function getSkill(): ActiveQuery
    {
        return $this->hasOne(ProgressSkill::class, ['id' => 'skill_id']);
    }

    public function getLesson(): ActiveQuery
    {
        return $this->hasOne(Lesson::class, ['id' => 'lesson_id']);
    }
}
