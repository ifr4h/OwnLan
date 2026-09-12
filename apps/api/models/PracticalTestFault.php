<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Fault ticks recorded from an official practical test sheet.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $practical_test_id
 * @property string $fault_type driving|serious|dangerous
 * @property string $fault_code
 * @property string $fault_label
 * @property string $area
 * @property string $aspect
 * @property string|null $skill_code
 * @property int $count
 * @property string $created_at
 *
 * @property-read PracticalTest $practicalTest
 */
class PracticalTestFault extends ActiveRecord
{
    public const TYPE_DRIVING = 'driving';
    public const TYPE_SERIOUS = 'serious';
    public const TYPE_DANGEROUS = 'dangerous';

    public const TYPES = [
        self::TYPE_DRIVING,
        self::TYPE_SERIOUS,
        self::TYPE_DANGEROUS,
    ];

    public static function tableName(): string
    {
        return '{{%practical_test_faults}}';
    }

    public function rules(): array
    {
        return [
            [
                [
                    'organisation_id',
                    'practical_test_id',
                    'fault_type',
                    'fault_code',
                    'fault_label',
                    'area',
                    'aspect',
                    'count',
                    'created_at',
                ],
                'required',
            ],
            [['organisation_id', 'practical_test_id', 'count'], 'integer'],
            [['count'], 'integer', 'min' => 1, 'max' => 99],
            [['fault_type'], 'in', 'range' => self::TYPES],
            [['fault_code', 'skill_code'], 'string', 'max' => 64],
            [['fault_label'], 'string', 'max' => 160],
            [['area', 'aspect'], 'string', 'max' => 64],
            [['created_at'], 'safe'],
        ];
    }

    public function getPracticalTest(): ActiveQuery
    {
        return $this->hasOne(PracticalTest::class, ['id' => 'practical_test_id']);
    }
}
