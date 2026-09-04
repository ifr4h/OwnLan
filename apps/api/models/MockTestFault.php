<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Single fault recorded during a mock test.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $mock_test_id
 * @property int $skill_id
 * @property string $fault_type driving|serious|dangerous
 * @property string $fault_code
 * @property string $fault_label
 * @property string|null $note
 * @property string $recorded_at
 * @property int|null $elapsed_seconds
 * @property int|null $route_moment_id
 * @property string|null $client_op_id
 * @property string|null $undone_at
 * @property string $created_at
 *
 * @property-read ProgressSkill $skill
 * @property-read MockTest $mockTest
 */
class MockTestFault extends ActiveRecord
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
        return '{{%mock_test_faults}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'mock_test_id', 'skill_id', 'fault_type', 'fault_code', 'fault_label', 'recorded_at', 'created_at'], 'required'],
            [['organisation_id', 'mock_test_id', 'skill_id', 'elapsed_seconds', 'route_moment_id'], 'integer'],
            [['fault_type'], 'in', 'range' => self::TYPES],
            [['fault_code'], 'string', 'max' => 64],
            [['fault_label'], 'string', 'max' => 160],
            [['note'], 'string'],
            [['client_op_id'], 'string', 'max' => 64],
            [['recorded_at', 'undone_at', 'created_at'], 'safe'],
        ];
    }

    public function getSkill(): ActiveQuery
    {
        return $this->hasOne(ProgressSkill::class, ['id' => 'skill_id']);
    }

    public function getMockTest(): ActiveQuery
    {
        return $this->hasOne(MockTest::class, ['id' => 'mock_test_id']);
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }
}
