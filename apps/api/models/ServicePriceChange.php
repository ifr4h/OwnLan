<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $service_id
 * @property int $price_pence
 * @property string $effective_on
 * @property string $created_at
 */
class ServicePriceChange extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%service_price_changes}}';
    }
}
