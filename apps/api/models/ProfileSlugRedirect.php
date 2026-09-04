<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $old_slug
 * @property string $created_at
 */
class ProfileSlugRedirect extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%profile_slug_redirects}}';
    }
}
