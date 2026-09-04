<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Explicit moment on a recorded lesson route (mark while recording or enrich later).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $lesson_route_id
 * @property int $lesson_id
 * @property int $learner_id
 * @property string $recorded_at
 * @property int|null $offset_seconds
 * @property float $lat
 * @property float $lng
 * @property string $kind
 * @property string|null $label
 * @property string|null $learner_note
 * @property bool $learner_visible
 * @property string $created_at
 * @property string $updated_at
 */
class RouteMoment extends ActiveRecord
{
    public const KIND_REVIEW = 'review';
    public const KIND_GOOD = 'good';
    public const KIND_EXPLAIN = 'explain';
    public const KIND_HAZARD = 'hazard';
    public const KIND_ROUNDABOUT = 'roundabout';
    public const KIND_CUSTOM = 'custom';

    public const KINDS = [
        self::KIND_REVIEW,
        self::KIND_GOOD,
        self::KIND_EXPLAIN,
        self::KIND_HAZARD,
        self::KIND_ROUNDABOUT,
        self::KIND_CUSTOM,
    ];

    public static function tableName(): string
    {
        return '{{%route_moments}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'lesson_route_id', 'lesson_id', 'learner_id', 'recorded_at', 'lat', 'lng', 'kind', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'lesson_route_id', 'lesson_id', 'learner_id', 'offset_seconds'], 'integer'],
            [['lat', 'lng'], 'number'],
            [['learner_visible'], 'boolean'],
            [['label'], 'string', 'max' => 200],
            [['learner_note'], 'string'],
            [['kind'], 'in', 'range' => self::KINDS],
            [['recorded_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }
}
