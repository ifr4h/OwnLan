<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $label
 * @property int $purchased_minutes
 * @property int $price_pence
 * @property bool $active
 * @property bool $portal_visible
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class PackageOffering extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%package_offerings}}';
    }
}
