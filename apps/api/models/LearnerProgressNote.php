<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Instructor note on a learner skill or skill category.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string $note_key
 * @property int|null $skill_id
 * @property string|null $category_code
 * @property string $body
 * @property bool $learner_visible
 * @property string $created_at
 * @property string $updated_at
 */
class LearnerProgressNote extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%learner_progress_notes}}';
    }

    public static function skillKey(int $skillId): string
    {
        return 'skill:' . $skillId;
    }

    public static function categoryKey(string $categoryCode): string
    {
        return 'category:' . $categoryCode;
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'note_key', 'body', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'learner_id', 'skill_id'], 'integer'],
            [['learner_visible'], 'boolean'],
            [['body'], 'string'],
            [['note_key'], 'string', 'max' => 96],
            [['category_code'], 'string', 'max' => 64],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }
}
