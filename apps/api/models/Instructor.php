<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $user_id
 * @property string $display_name
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Organisation $organisation
 * @property-read User $user
 */
class Instructor extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%instructors}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'user_id', 'display_name', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'user_id'], 'integer'],
            [['display_name'], 'string', 'max' => 255],
        ];
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
