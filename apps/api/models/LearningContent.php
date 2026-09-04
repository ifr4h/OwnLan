<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Structured learning library item (platform or org-owned).
 *
 * @property int $id
 * @property int|null $organisation_id
 * @property string $slug
 * @property string $title
 * @property string $category
 * @property string|null $summary
 * @property string $blocks_json
 * @property string|null $skill_codes_json
 * @property string|null $transmission
 * @property string $status
 * @property int $version
 * @property string|null $published_at
 * @property string|null $source_note
 * @property string $created_at
 * @property string $updated_at
 */
class LearningContent extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public static function tableName(): string
    {
        return '{{%learning_contents}}';
    }

    public function rules(): array
    {
        return [
            [['slug', 'title', 'category', 'blocks_json', 'status', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'version'], 'integer'],
            [['summary', 'blocks_json', 'skill_codes_json'], 'string'],
            [['slug'], 'string', 'max' => 120],
            [['title'], 'string', 'max' => 200],
            [['category'], 'string', 'max' => 64],
            [['transmission'], 'string', 'max' => 16],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED]],
            [['source_note'], 'string', 'max' => 255],
            [['published_at', 'created_at', 'updated_at'], 'safe'],
            [['slug'], 'unique'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function blocks(): array
    {
        try {
            $decoded = json_decode($this->blocks_json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
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
