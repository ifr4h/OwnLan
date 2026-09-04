<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Learner-logged private practice — never mutates instructor skill ratings.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string $practised_at
 * @property int $duration_minutes
 * @property string|null $skill_codes_json
 * @property string|null $feeling
 * @property string|null $note
 * @property int|null $lesson_route_id
 * @property string|null $companion_note
 * @property int|null $companion_account_id
 * @property string $created_at
 * @property string $updated_at
 */
class PrivatePracticeSession extends ActiveRecord
{
    public const FEELING_DIFFICULT = 'difficult';
    public const FEELING_OKAY = 'okay';
    public const FEELING_COMFORTABLE = 'comfortable';

    public static function tableName(): string
    {
        return '{{%private_practice_sessions}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'practised_at', 'duration_minutes', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'learner_id', 'duration_minutes'], 'integer'],
            [['duration_minutes'], 'integer', 'min' => 5, 'max' => 480],
            [['skill_codes_json', 'note', 'companion_note'], 'string'],
            [['lesson_route_id', 'companion_account_id'], 'integer'],
            [['feeling'], 'in', 'range' => [
                self::FEELING_DIFFICULT,
                self::FEELING_OKAY,
                self::FEELING_COMFORTABLE,
            ]],
            [['practised_at', 'created_at', 'updated_at'], 'safe'],
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
