<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Learner-reported confidence for a skill — separate from instructor assessment.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int $skill_id
 * @property string $confidence
 * @property string|null $note
 * @property string $recorded_at
 * @property string $created_at
 *
 * @property-read ProgressSkill $skill
 */
class LearnerSkillSelfAssessment extends ActiveRecord
{
    public const CONFIDENCE_NEED_HELP = 'need_more_help';
    public const CONFIDENCE_STILL_PRACTISING = 'still_practising';
    public const CONFIDENCE_GETTING_COMFORTABLE = 'getting_comfortable';
    public const CONFIDENCE_CONFIDENT = 'feel_confident';

    public const CONFIDENCES = [
        self::CONFIDENCE_NEED_HELP,
        self::CONFIDENCE_STILL_PRACTISING,
        self::CONFIDENCE_GETTING_COMFORTABLE,
        self::CONFIDENCE_CONFIDENT,
    ];

    public static function tableName(): string
    {
        return '{{%learner_skill_self_assessments}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'skill_id', 'confidence', 'recorded_at', 'created_at'], 'required'],
            [['organisation_id', 'learner_id', 'skill_id'], 'integer'],
            [['confidence'], 'in', 'range' => self::CONFIDENCES],
            [['note'], 'string'],
            [['recorded_at', 'created_at'], 'safe'],
        ];
    }

    public function getSkill(): ActiveQuery
    {
        return $this->hasOne(ProgressSkill::class, ['id' => 'skill_id']);
    }

    public static function labelFor(string $confidence): string
    {
        return match ($confidence) {
            self::CONFIDENCE_NEED_HELP => 'I need more help',
            self::CONFIDENCE_STILL_PRACTISING => 'I still need practice',
            self::CONFIDENCE_GETTING_COMFORTABLE => 'I\'m getting more comfortable',
            self::CONFIDENCE_CONFIDENT => 'I feel confident',
            default => $confidence,
        };
    }
}
