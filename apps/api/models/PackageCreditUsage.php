<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Package minutes consumed against a completed lesson.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int $package_id
 * @property int $lesson_id
 * @property int $minutes
 * @property string $created_at
 * @property string|null $voided_at
 * @property string|null $void_reason
 */
class PackageCreditUsage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%package_credit_usages}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'package_id', 'lesson_id', 'minutes', 'created_at'], 'required'],
            [['organisation_id', 'learner_id', 'package_id', 'lesson_id', 'minutes'], 'integer'],
            [['void_reason'], 'string'],
            [['created_at', 'voided_at'], 'safe'],
        ];
    }
}
