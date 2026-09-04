<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Optional monthly teaching income target.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $period_year
 * @property int $period_month 1–12
 * @property int $target_pence
 * @property string $created_at
 * @property string $updated_at
 */
class FinancialGoal extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%financial_goals}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'period_year',
                'period_month',
                'target_pence',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'period_year', 'period_month', 'target_pence'], 'integer'],
            [['period_month'], 'integer', 'min' => 1, 'max' => 12],
            [['target_pence'], 'integer', 'min' => 1],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }
}
