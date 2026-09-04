<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Historical skill rating for a learner (append-only progress evidence).
 *
 * Ratings: introduced | practising | developing | confident
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int $skill_id
 * @property int|null $lesson_id
 * @property string $rating
 * @property string $recorded_at
 * @property string $created_at
 *
 * @property-read ProgressSkill $skill
 * @property-read Lesson|null $lesson
 */
class LearnerSkillProgress extends ActiveRecord
{
    public const RATING_INTRODUCED = 'introduced';
    public const RATING_PRACTISING = 'practising';
    public const RATING_DEVELOPING = 'developing';
    public const RATING_CONFIDENT = 'confident';

    public const RATINGS = [
        self::RATING_INTRODUCED,
        self::RATING_PRACTISING,
        self::RATING_DEVELOPING,
        self::RATING_CONFIDENT,
    ];

    /** Ordinal for trend comparisons — never expose as a readiness score. */
    public const RATING_RANK = [
        self::RATING_INTRODUCED => 1,
        self::RATING_PRACTISING => 2,
        self::RATING_DEVELOPING => 3,
        self::RATING_CONFIDENT => 4,
    ];

    public static function tableName(): string
    {
        return '{{%learner_skill_progress}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'skill_id', 'rating', 'recorded_at', 'created_at'], 'required'],
            [['organisation_id', 'learner_id', 'skill_id', 'lesson_id'], 'integer'],
            [['rating'], 'in', 'range' => self::RATINGS],
            [['recorded_at', 'created_at'], 'safe'],
        ];
    }

    public function getSkill(): ActiveQuery
    {
        return $this->hasOne(ProgressSkill::class, ['id' => 'skill_id']);
    }

    public function getLesson(): ActiveQuery
    {
        return $this->hasOne(Lesson::class, ['id' => 'lesson_id']);
    }
}
