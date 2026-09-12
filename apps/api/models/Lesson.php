<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $instructor_id
 * @property int $learner_id
 * @property int|null $series_id
 * @property string $starts_at UTC datetime Y-m-d H:i:s
 * @property int $duration_minutes
 * @property string|null $pickup_address
 * @property string $status
 * @property string|null $instructor_notes
 * @property string|null $learner_summary
 * @property string|null $next_focus
 * @property string|null $client_mutation_id
 * @property string|null $cancelled_at
 * @property string|null $cancelled_by instructor|learner|system
 * @property string|null $cancellation_reason
 * @property int|null $cancellation_notice_hours Hours of notice given when cancelled
 * @property string|null $completed_at
 * @property string|null $no_show_at
 * @property int|null $price_pence
 * @property int|null $service_id
 * @property string|null $settlement package|outstanding|paid|waived — money/credit outcome, not lesson status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Organisation $organisation
 * @property-read Instructor $instructor
 * @property-read Learner $learner
 * @property-read LessonSeries|null $series
 */
class Lesson extends ActiveRecord
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const CANCELLED_BY_INSTRUCTOR = 'instructor';
    public const CANCELLED_BY_LEARNER = 'learner';
    public const CANCELLED_BY_SYSTEM = 'system';

    public const PICKUP_BY_INSTRUCTOR = 'instructor';
    public const PICKUP_BY_LEARNER = 'learner';
    public const PICKUP_BY_SYSTEM = 'system';

    public const DEFAULT_DURATION_MINUTES = 60;

    public static function tableName(): string
    {
        return '{{%lessons}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'instructor_id',
                'learner_id',
                'starts_at',
                'duration_minutes',
                'status',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'instructor_id', 'learner_id', 'series_id', 'duration_minutes', 'price_pence', 'pickup_location_id', 'service_id', 'cancellation_notice_hours'], 'integer'],
            [['duration_minutes'], 'integer', 'min' => 15, 'max' => 480],
            [['price_pence'], 'integer', 'min' => 0],
            [['cancellation_notice_hours'], 'integer', 'min' => 0, 'max' => 720],
            [['pickup_address', 'instructor_notes', 'learner_summary', 'next_focus', 'focus_tags_json'], 'string'],
            [['pickup_set_by'], 'in', 'range' => [
                self::PICKUP_BY_INSTRUCTOR,
                self::PICKUP_BY_LEARNER,
                self::PICKUP_BY_SYSTEM,
            ]],
            [['client_mutation_id'], 'string', 'max' => 64],
            [['cancellation_reason'], 'string', 'max' => 500],
            [['cancelled_by'], 'in', 'range' => [
                self::CANCELLED_BY_INSTRUCTOR,
                self::CANCELLED_BY_LEARNER,
                self::CANCELLED_BY_SYSTEM,
            ]],
            [['settlement'], 'string', 'max' => 32],
            [['status'], 'in', 'range' => [
                self::STATUS_SCHEDULED,
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED,
                self::STATUS_NO_SHOW,
            ]],
            [[
                'starts_at',
                'cancelled_at',
                'completed_at',
                'no_show_at',
                'pickup_changed_at',
                'pickup_change_acked_at',
                'created_at',
                'updated_at',
            ], 'safe'],
        ];
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function getInstructor(): ActiveQuery
    {
        return $this->hasOne(Instructor::class, ['id' => 'instructor_id']);
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function getSeries(): ActiveQuery
    {
        return $this->hasOne(LessonSeries::class, ['id' => 'series_id']);
    }

    public function getPickupLocation(): ActiveQuery
    {
        return $this->hasOne(LearnerLocation::class, ['id' => 'pickup_location_id']);
    }

    /**
     * @return list<string>
     */
    public function focusTags(): array
    {
        if ($this->focus_tags_json === null || $this->focus_tags_json === '') {
            return [];
        }
        $decoded = json_decode($this->focus_tags_json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            $decoded,
        ), static fn (string $v) => $v !== ''));
    }

    public function pickupChangePending(): bool
    {
        if ($this->pickup_changed_at === null) {
            return false;
        }
        if ($this->pickup_change_acked_at === null) {
            return true;
        }

        return $this->pickup_changed_at > $this->pickup_change_acked_at;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }
}
