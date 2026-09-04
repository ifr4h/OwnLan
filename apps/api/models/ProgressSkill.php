<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Canonical DVSA-aligned skill definition (platform-wide, not tenant-scoped).
 *
 * @property int $id
 * @property string $code
 * @property string $category_code
 * @property string $category_label
 * @property string $label
 * @property int $sort_order
 * @property bool $active
 */
class ProgressSkill extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%progress_skills}}';
    }

    public function rules(): array
    {
        return [
            [['code', 'category_code', 'category_label', 'label'], 'required'],
            [['sort_order'], 'integer'],
            [['active'], 'boolean'],
            [['code', 'category_code'], 'string', 'max' => 64],
            [['category_label'], 'string', 'max' => 120],
            [['label'], 'string', 'max' => 160],
            [['code'], 'unique'],
        ];
    }
}
