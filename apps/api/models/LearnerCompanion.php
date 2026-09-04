<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Learner ↔ Companion link with explicit permission scopes.
 *
 * DEFERRED / NOT CURRENTLY EXPOSED — broad Companion product disabled for beta.
 * See docs/17-companion-access-decision.md.
 *
 * Permissions JSON keys (booleans):
 * lessons, money, progress, learn, routes, test, practice, booking
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int $companion_account_id
 * @property string $display_name
 * @property string|null $relationship_label
 * @property string $permissions_json
 * @property string $invited_by
 * @property string|null $revoked_at
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read CompanionAccount $companionAccount
 * @property-read Learner $learner
 */
class LearnerCompanion extends ActiveRecord
{
    public const PERMISSIONS = [
        'lessons',
        'money',
        'progress',
        'learn',
        'routes',
        'test',
        'practice',
        'booking',
    ];

    public static function tableName(): string
    {
        return '{{%learner_companions}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'companion_account_id', 'display_name', 'permissions_json', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'learner_id', 'companion_account_id'], 'integer'],
            [['display_name'], 'string', 'max' => 120],
            [['relationship_label'], 'string', 'max' => 40],
            [['permissions_json'], 'string'],
            [['invited_by'], 'string', 'max' => 16],
            [['revoked_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getCompanionAccount(): ActiveQuery
    {
        return $this->hasOne(CompanionAccount::class, ['id' => 'companion_account_id']);
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /** @return array<string, bool> */
    public function permissions(): array
    {
        try {
            $decoded = json_decode($this->permissions_json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        $out = [];
        foreach (self::PERMISSIONS as $key) {
            $out[$key] = !empty($decoded[$key]);
        }

        return $out;
    }

    public function can(string $permission): bool
    {
        return $this->isActive() && !empty($this->permissions()[$permission]);
    }
}
