<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Reusable teaching board / annotated map master.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int|null $created_by_instructor_id
 * @property string $kind
 * @property string $title
 * @property string|null $category
 * @property string|null $description
 * @property string|null $template_code
 * @property string $scene_json
 * @property string|null $skill_codes_json
 * @property bool $is_favourite
 * @property string|null $archived_at
 * @property string $created_at
 * @property string $updated_at
 */
class TeachingResource extends ActiveRecord
{
    public const KIND_BOARD = 'board';
    public const KIND_REAL_ROAD = 'real_road';

    public static function tableName(): string
    {
        return '{{%teaching_resources}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'kind', 'title', 'scene_json', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'created_by_instructor_id'], 'integer'],
            [['is_favourite'], 'boolean'],
            [['description', 'scene_json', 'skill_codes_json'], 'string'],
            [['kind'], 'string', 'max' => 32],
            [['title'], 'string', 'max' => 200],
            [['category', 'template_code'], 'string', 'max' => 64],
            [['archived_at', 'created_at', 'updated_at'], 'safe'],
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

    /** @return array<string, mixed> */
    public function scene(): array
    {
        try {
            $decoded = json_decode($this->scene_json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
