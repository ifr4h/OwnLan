<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Official DVSA practical driving test result logged by the instructor.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $instructor_id
 * @property int $learner_id
 * @property string $test_date
 * @property string $result pass|fail
 * @property string|null $test_centre
 * @property bool $accompanied
 * @property bool $examiner_action
 * @property int $driving_faults_count
 * @property int $serious_faults_count
 * @property int $dangerous_faults_count
 * @property string|null $instructor_notes
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Learner $learner
 * @property-read PracticalTestFault[] $faults
 */
class PracticalTest extends ActiveRecord
{
    public const RESULT_PASS = 'pass';
    public const RESULT_FAIL = 'fail';

    public const RESULTS = [
        self::RESULT_PASS,
        self::RESULT_FAIL,
    ];

    public static function tableName(): string
    {
        return '{{%practical_tests}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'instructor_id', 'learner_id', 'test_date', 'result', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'instructor_id', 'learner_id'], 'integer'],
            [['driving_faults_count', 'serious_faults_count', 'dangerous_faults_count'], 'integer', 'min' => 0],
            [['result'], 'in', 'range' => self::RESULTS],
            [['test_centre'], 'string', 'max' => 255],
            [['instructor_notes'], 'string'],
            [['accompanied', 'examiner_action'], 'boolean'],
            [['test_date', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function getFaults(): ActiveQuery
    {
        return $this->hasMany(PracticalTestFault::class, ['practical_test_id' => 'id'])
            ->orderBy(['area' => SORT_ASC, 'aspect' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function isPass(): bool
    {
        return $this->result === self::RESULT_PASS;
    }
}
