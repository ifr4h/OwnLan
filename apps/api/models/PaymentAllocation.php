<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $payment_id
 * @property int $lesson_charge_id
 * @property int $amount_pence
 * @property string $created_at
 */
class PaymentAllocation extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_allocations}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'payment_id', 'lesson_charge_id', 'amount_pence', 'created_at'], 'required'],
            [['organisation_id', 'payment_id', 'lesson_charge_id', 'amount_pence'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }
}
