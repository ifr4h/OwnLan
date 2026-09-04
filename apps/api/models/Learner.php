<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Operational pupil record (domain: Learner). Portal auth is separate.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $first_name
 * @property string $last_name
 * @property string $mobile
 * @property string|null $email
 * @property string|null $default_pickup_address
 * @property string|null $test_date
 * @property string|null $test_centre
 * @property string|null $private_notes
 * @property string|null $next_focus
 * @property string|null $last_lesson_summary
 * @property string $lifecycle active|waiting
 * @property string|null $waiting_list_joined_at
 * @property string|null $transmission
 * @property string|null $theory_status
 * @property string|null $theory_pass_date
 * @property string|null $practical_test_time
 * @property bool $availability_is_variable
 * @property string|null $preferred_contact
 * @property int|null $intake_id
 * @property string|null $learner_reported_json
 * @property string|null $terms_acknowledged_at
 * @property string|null $archived_at
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Organisation $organisation
 * @property-read string $fullName
 * @property-read bool $isArchived
 * @property-read bool $isWaiting
 */
class Learner extends ActiveRecord
{
    public const LIFECYCLE_ACTIVE = 'active';
    public const LIFECYCLE_WAITING = 'waiting';

    public static function tableName(): string
    {
        return '{{%learners}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'first_name', 'last_name', 'mobile', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'intake_id'], 'integer'],
            [['first_name', 'last_name'], 'string', 'max' => 100],
            [['mobile'], 'string', 'max' => 32],
            [['email', 'test_centre'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['default_pickup_address', 'private_notes', 'next_focus', 'last_lesson_summary', 'learner_reported_json'], 'string'],
            [['test_date', 'theory_pass_date'], 'date', 'format' => 'php:Y-m-d'],
            [['practical_test_time'], 'string', 'max' => 5],
            [['transmission'], 'in', 'range' => ['manual', 'automatic', 'either']],
            [['theory_status'], 'in', 'range' => ['passed', 'not_yet', 'booked']],
            [['preferred_contact'], 'in', 'range' => ['sms', 'whatsapp', 'email', 'call']],
            [['lifecycle'], 'in', 'range' => [self::LIFECYCLE_ACTIVE, self::LIFECYCLE_WAITING]],
            [['availability_is_variable'], 'boolean'],
            [[
                'waiting_list_joined_at',
                'terms_acknowledged_at',
                'archived_at',
                'created_at',
                'updated_at',
            ], 'safe'],
        ];
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getIsArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getIsWaiting(): bool
    {
        return $this->lifecycle === self::LIFECYCLE_WAITING;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLearnerReported(): array
    {
        if ($this->learner_reported_json === null || $this->learner_reported_json === '') {
            return [];
        }
        $decoded = json_decode($this->learner_reported_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => (int) $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'default_pickup_address' => $this->default_pickup_address,
            'test_date' => $this->test_date,
            'test_centre' => $this->test_centre,
            'practical_test_time' => $this->practical_test_time,
            'private_notes' => $this->private_notes,
            'next_focus' => $this->next_focus,
            'last_lesson_summary' => $this->last_lesson_summary,
            'lifecycle' => $this->lifecycle ?: self::LIFECYCLE_ACTIVE,
            'waiting_list_joined_at' => $this->waiting_list_joined_at,
            'transmission' => $this->transmission,
            'theory_status' => $this->theory_status,
            'theory_pass_date' => $this->theory_pass_date,
            'availability_is_variable' => (bool) $this->availability_is_variable,
            'preferred_contact' => $this->preferred_contact,
            'intake_id' => $this->intake_id !== null ? (int) $this->intake_id : null,
            'learner_reported' => $this->learnerReported,
            'terms_acknowledged_at' => $this->terms_acknowledged_at,
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => (int) $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'default_pickup_address' => $this->default_pickup_address,
            'test_date' => $this->test_date,
            'next_focus' => $this->next_focus,
            'lifecycle' => $this->lifecycle ?: self::LIFECYCLE_ACTIVE,
            'waiting_list_joined_at' => $this->waiting_list_joined_at,
            'transmission' => $this->transmission,
            'archived_at' => $this->archived_at,
        ];
    }
}
