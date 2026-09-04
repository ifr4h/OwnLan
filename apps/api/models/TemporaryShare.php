<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Constrained temporary share of one learner-visible resource.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string $resource_type
 * @property int $resource_id
 * @property string $token_hash
 * @property string $expires_at
 * @property string|null $revoked_at
 * @property string $created_at
 */
class TemporaryShare extends ActiveRecord
{
    public const TYPE_ROUTE = 'route';
    public const TYPE_LESSON_RESOURCE = 'lesson_resource';
    public const TYPE_CONTENT = 'content';
    public const TYPE_PRACTICE_PLAN = 'practice_plan';

    public static function tableName(): string
    {
        return '{{%temporary_shares}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'resource_type', 'resource_id', 'token_hash', 'expires_at', 'created_at'], 'required'],
            [['organisation_id', 'learner_id', 'resource_id'], 'integer'],
            [['resource_type'], 'string', 'max' => 32],
            [['token_hash'], 'string', 'max' => 64],
            [['expires_at', 'revoked_at', 'created_at'], 'safe'],
        ];
    }

    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at >= gmdate('Y-m-d H:i:s');
    }
}
