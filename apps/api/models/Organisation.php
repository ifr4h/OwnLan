<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $timezone
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Membership[] $memberships
 * @property-read Instructor[] $instructors
 */
class Organisation extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%organisations}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'timezone', 'created_at', 'updated_at'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['timezone'], 'string', 'max' => 64],
        ];
    }

    public function getMemberships(): ActiveQuery
    {
        return $this->hasMany(Membership::class, ['organisation_id' => 'id']);
    }

    public function getInstructors(): ActiveQuery
    {
        return $this->hasMany(Instructor::class, ['organisation_id' => 'id']);
    }
}
