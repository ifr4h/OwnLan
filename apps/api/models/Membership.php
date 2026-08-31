<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $user_id
 * @property int $organisation_id
 * @property string $role
 * @property string $created_at
 *
 * @property-read User $user
 * @property-read Organisation $organisation
 */
class Membership extends ActiveRecord
{
    public const ROLE_OWNER = 'owner';

    public static function tableName(): string
    {
        return '{{%memberships}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'organisation_id', 'role', 'created_at'], 'required'],
            [['user_id', 'organisation_id'], 'integer'],
            [['role'], 'string', 'max' => 32],
            [['role'], 'in', 'range' => [self::ROLE_OWNER]],
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }
}
