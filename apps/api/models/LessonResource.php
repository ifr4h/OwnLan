<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Lesson-specific teaching snapshot — never overwrites TeachingResource master.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $lesson_id
 * @property int $learner_id
 * @property int|null $source_resource_id
 * @property string $kind
 * @property string $title
 * @property string $scene_json
 * @property string|null $learner_visible_note
 * @property string|null $skill_codes_json
 * @property int|null $route_moment_id
 * @property bool $learner_visible
 * @property string $created_at
 * @property string $updated_at
 */
class LessonResource extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%lesson_resources}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'lesson_id', 'learner_id', 'kind', 'title', 'scene_json', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'lesson_id', 'learner_id', 'source_resource_id', 'route_moment_id'], 'integer'],
            [['learner_visible'], 'boolean'],
            [['scene_json', 'learner_visible_note', 'skill_codes_json'], 'string'],
            [['kind'], 'string', 'max' => 32],
            [['title'], 'string', 'max' => 200],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    /** @return list<string> */
    public function skillCodes(): array
    {
        if ($this->skill_codes_json === null || $this->skill_codes_json === '') {
            return [];
        }
        try {
            $decoded = json_decode($this->skill_codes_json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}
