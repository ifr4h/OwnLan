<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Learner-initiated booking or reschedule request — not a diary lesson until accepted.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $instructor_id
 * @property int $learner_id
 * @property string $type book|reschedule
 * @property int|null $original_lesson_id
 * @property string $requested_starts_at UTC
 * @property int $duration_minutes
 * @property string|null $pickup_address
 * @property string $status
 * @property string|null $suggested_starts_at UTC
 * @property string|null $decline_reason
 * @property int|null $lesson_id
 * @property string|null $client_mutation_id
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $responded_at
 * @property string|null $expires_at
 *
 * @property-read Organisation $organisation
 * @property-read Instructor $instructor
 * @property-read Learner $learner
 * @property-read Lesson|null $lesson
 * @property-read Lesson|null $originalLesson
 */
class LessonBookingRequest extends ActiveRecord
{
    public const TYPE_BOOK = 'book';
    public const TYPE_RESCHEDULE = 'reschedule';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COUNTER_PROPOSED = 'counter_proposed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_EXPIRED = 'expired';

    public static function tableName(): string
    {
        return '{{%lesson_booking_requests}}';
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'instructor_id',
                'learner_id',
                'requested_starts_at',
                'duration_minutes',
                'status',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'instructor_id', 'learner_id', 'original_lesson_id', 'lesson_id', 'duration_minutes'], 'integer'],
            [['pickup_address', 'decline_reason'], 'string'],
            [['type'], 'in', 'range' => [self::TYPE_BOOK, self::TYPE_RESCHEDULE]],
            [['status'], 'in', 'range' => [
                self::STATUS_PENDING,
                self::STATUS_COUNTER_PROPOSED,
                self::STATUS_ACCEPTED,
                self::STATUS_DECLINED,
                self::STATUS_WITHDRAWN,
                self::STATUS_EXPIRED,
            ]],
            [['client_mutation_id'], 'string', 'max' => 64],
            [[
                'requested_starts_at',
                'suggested_starts_at',
                'created_at',
                'updated_at',
                'responded_at',
                'expires_at',
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

    public function getLesson(): ActiveQuery
    {
        return $this->hasOne(Lesson::class, ['id' => 'lesson_id']);
    }

    public function getOriginalLesson(): ActiveQuery
    {
        return $this->hasOne(Lesson::class, ['id' => 'original_lesson_id']);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_COUNTER_PROPOSED], true);
    }
}
