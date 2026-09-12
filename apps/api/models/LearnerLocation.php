<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Saved pickup place for a pupil (shared with their instructor).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string $label
 * @property string $icon
 * @property string $address
 * @property string $usage pickup|dropoff|both
 * @property bool $is_default
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Learner $learner
 */
class LearnerLocation extends ActiveRecord
{
    public const ICONS = [
        'home',
        'work',
        'school',
        'gym',
        'pin',
        'star',
        'train',
        'car',
    ];

    public const USAGE_PICKUP = 'pickup';
    public const USAGE_DROPOFF = 'dropoff';
    public const USAGE_BOTH = 'both';

    public static function tableName(): string
    {
        return '{{%learner_locations}}';
    }

    /**
     * @return list<string>
     */
    public static function usages(): array
    {
        return [self::USAGE_PICKUP, self::USAGE_DROPOFF, self::USAGE_BOTH];
    }

    public static function usageLabel(string $usage): string
    {
        return match ($usage) {
            self::USAGE_PICKUP => 'Pickup',
            self::USAGE_DROPOFF => 'Drop-off',
            default => 'Pickup & drop-off',
        };
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'label', 'address', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'learner_id', 'sort_order'], 'integer'],
            [['label'], 'string', 'max' => 64],
            [['icon'], 'string', 'max' => 32],
            [['icon'], 'in', 'range' => self::ICONS],
            [['usage'], 'in', 'range' => self::usages()],
            [['address'], 'string'],
            [['is_default'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }
}
