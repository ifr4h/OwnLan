<?php

declare(strict_types=1);

namespace app\models;

use DateTimeImmutable;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Operational pupil record (domain: Learner). Portal auth is separate.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string $mobile
 * @property string|null $email
 * @property string|null $default_pickup_address
 * @property string|null $test_date
 * @property string|null $test_centre
 * @property string|null $private_notes
 * @property string|null $next_focus
 * @property string|null $last_lesson_summary
 * @property string $lifecycle active|waiting|paused|passed
 * @property string|null $waiting_list_joined_at
 * @property string|null $transmission
 * @property string|null $theory_status
 * @property string|null $theory_pass_date
 * @property string|null $theory_test_date
 * @property string|null $practical_test_time
 * @property string|null $practical_test_booking_ref
 * @property string|null $practical_test_cancel_by
 * @property string|null $practical_test_reminder_offsets
 * @property string|null $licence_number
 * @property string|null $licence_expiry_date
 * @property string|null $date_of_birth
 * @property string|null $gender
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $eyesight_status
 * @property string|null $eyesight_checked_on
 * @property bool $wears_glasses
 * @property string|null $medical_notes
 * @property string|null $referred_by
 * @property string|null $payment_notes
 * @property string|null $available_from
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
    public const LIFECYCLE_PAUSED = 'paused';
    public const LIFECYCLE_PASSED = 'passed';

    /** Display statuses instructors manage (inactive = archived). */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_PASSED = 'passed';
    public const STATUS_INACTIVE = 'inactive';
    /** List filter only — not a settable learner status. */
    public const STATUS_ALL = 'all';

    public const GENDER_FEMALE = 'female';
    public const GENDER_MALE = 'male';
    public const GENDER_NON_BINARY = 'non_binary';
    public const GENDER_PREFER_NOT = 'prefer_not_to_say';

    public const EYESIGHT_NOT_CHECKED = 'not_checked';
    public const EYESIGHT_CHECKED_OK = 'checked_ok';
    public const EYESIGHT_GLASSES = 'glasses';
    public const EYESIGHT_FAILED = 'failed';

    public static function tableName(): string
    {
        return '{{%learners}}';
    }

    /**
     * @return list<string>
     */
    public static function genderValues(): array
    {
        return [
            self::GENDER_FEMALE,
            self::GENDER_MALE,
            self::GENDER_NON_BINARY,
            self::GENDER_PREFER_NOT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function eyesightValues(): array
    {
        return [
            self::EYESIGHT_NOT_CHECKED,
            self::EYESIGHT_CHECKED_OK,
            self::EYESIGHT_GLASSES,
            self::EYESIGHT_FAILED,
        ];
    }

    public static function eyesightLabel(?string $status): ?string
    {
        return match ($status) {
            self::EYESIGHT_NOT_CHECKED => 'Not checked yet',
            self::EYESIGHT_CHECKED_OK => 'Checked · fine',
            self::EYESIGHT_GLASSES => 'Needs glasses / lenses for driving',
            self::EYESIGHT_FAILED => 'Failed / recheck needed',
            default => null,
        };
    }

    public static function genderLabel(?string $gender): ?string
    {
        return match ($gender) {
            self::GENDER_FEMALE => 'Female',
            self::GENDER_MALE => 'Male',
            self::GENDER_NON_BINARY => 'Non-binary',
            self::GENDER_PREFER_NOT => 'Prefer not to say',
            default => null,
        };
    }

    /**
     * Age in full years from date of birth, or null if unknown / invalid.
     */
    public function ageYears(?DateTimeImmutable $on = null): ?int
    {
        if ($this->date_of_birth === null || $this->date_of_birth === '') {
            return null;
        }
        try {
            $dob = new DateTimeImmutable($this->date_of_birth);
        } catch (\Exception) {
            return null;
        }
        $on ??= new DateTimeImmutable('today');
        if ($dob > $on) {
            return null;
        }

        return (int) $dob->diff($on)->y;
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'first_name', 'last_name', 'mobile', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'intake_id'], 'integer'],
            [['first_name', 'middle_name', 'last_name'], 'string', 'max' => 100],
            [['mobile'], 'string', 'max' => 32],
            [['email', 'test_centre'], 'string', 'max' => 255],
            [['licence_number'], 'string', 'max' => 32],
            [['emergency_contact_name'], 'string', 'max' => 120],
            [['emergency_contact_phone'], 'string', 'max' => 32],
            [['date_of_birth', 'eyesight_checked_on'], 'date', 'format' => 'php:Y-m-d'],
            [['gender'], 'in', 'range' => self::genderValues(), 'skipOnEmpty' => true],
            [['eyesight_status'], 'in', 'range' => self::eyesightValues(), 'skipOnEmpty' => true],
            [['wears_glasses'], 'boolean'],
            [['medical_notes', 'payment_notes'], 'string'],
            [['referred_by'], 'string', 'max' => 120],
            [['practical_test_booking_ref'], 'string', 'max' => 64],
            [['email'], 'email'],
            [['default_pickup_address', 'private_notes', 'next_focus', 'last_lesson_summary', 'learner_reported_json', 'practical_test_reminder_offsets'], 'string'],
            [[
                'test_date',
                'theory_pass_date',
                'theory_test_date',
                'practical_test_cancel_by',
                'licence_expiry_date',
                'available_from',
            ], 'date', 'format' => 'php:Y-m-d'],
            [['practical_test_time'], 'string', 'max' => 5],
            [['transmission'], 'in', 'range' => ['manual', 'automatic', 'either']],
            [['theory_status'], 'in', 'range' => ['passed', 'not_yet', 'booked']],
            [['preferred_contact'], 'in', 'range' => ['sms', 'whatsapp', 'email', 'call']],
            [['lifecycle'], 'in', 'range' => [
                self::LIFECYCLE_ACTIVE,
                self::LIFECYCLE_WAITING,
                self::LIFECYCLE_PAUSED,
                self::LIFECYCLE_PASSED,
            ]],
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

    /**
     * @return list<string>
     */
    public static function lifecycleValues(): array
    {
        return [
            self::LIFECYCLE_ACTIVE,
            self::LIFECYCLE_WAITING,
            self::LIFECYCLE_PAUSED,
            self::LIFECYCLE_PASSED,
        ];
    }

    /**
     * Instructor-facing status codes, including inactive (archived).
     *
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_WAITING,
            self::STATUS_PAUSED,
            self::STATUS_PASSED,
            self::STATUS_INACTIVE,
        ];
    }

    /**
     * Status values allowed on the pupils list filter (includes "all").
     *
     * @return list<string>
     */
    public static function listStatusValues(): array
    {
        return array_merge([self::STATUS_ALL], self::statusValues());
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function getFullName(): string
    {
        $parts = array_filter([
            trim((string) $this->first_name),
            trim((string) ($this->middle_name ?? '')),
            trim((string) $this->last_name),
        ], static fn (string $p): bool => $p !== '');

        return implode(' ', $parts);
    }

    /**
     * @return list<int>
     */
    public function reminderOffsets(): array
    {
        $raw = trim((string) ($this->practical_test_reminder_offsets ?? ''));
        if ($raw === '') {
            return [7, 1, 0];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [7, 1, 0];
        }
        $out = [];
        foreach ($decoded as $v) {
            if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                $out[] = (int) $v;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : [7, 1, 0];
    }

    public function getIsArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getIsWaiting(): bool
    {
        return !$this->isArchived && $this->lifecycle === self::LIFECYCLE_WAITING;
    }

    /**
     * Resolved display status for lists and records.
     */
    public function resolveStatus(): string
    {
        if ($this->isArchived) {
            return self::STATUS_INACTIVE;
        }

        return $this->lifecycle ?: self::LIFECYCLE_ACTIVE;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_WAITING => 'On waitlist',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_PASSED => 'Passed',
            self::STATUS_INACTIVE => 'Inactive',
            default => 'Active',
        };
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
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'default_pickup_address' => $this->default_pickup_address,
            'test_date' => $this->test_date,
            'test_centre' => $this->test_centre,
            'practical_test_time' => $this->practical_test_time,
            'practical_test_booking_ref' => $this->practical_test_booking_ref,
            'practical_test_cancel_by' => $this->practical_test_cancel_by,
            'practical_test_reminder_offsets' => $this->reminderOffsets(),
            'private_notes' => $this->private_notes,
            'next_focus' => $this->next_focus,
            'last_lesson_summary' => $this->last_lesson_summary,
            'lifecycle' => $this->lifecycle ?: self::LIFECYCLE_ACTIVE,
            'status' => $this->resolveStatus(),
            'status_label' => self::statusLabel($this->resolveStatus()),
            'waiting_list_joined_at' => $this->waiting_list_joined_at,
            'transmission' => $this->transmission,
            'theory_status' => $this->theory_status,
            'theory_pass_date' => $this->theory_pass_date,
            'theory_test_date' => $this->theory_test_date,
            'licence_number' => $this->licence_number,
            'licence_expiry_date' => $this->licence_expiry_date,
            'date_of_birth' => $this->date_of_birth,
            'age_years' => $this->ageYears(),
            'gender' => $this->gender,
            'gender_label' => self::genderLabel($this->gender),
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'eyesight_status' => $this->eyesight_status,
            'eyesight_status_label' => self::eyesightLabel($this->eyesight_status),
            'eyesight_checked_on' => $this->eyesight_checked_on,
            'wears_glasses' => (bool) $this->wears_glasses,
            'medical_notes' => $this->medical_notes,
            'referred_by' => $this->referred_by,
            'payment_notes' => $this->payment_notes,
            'available_from' => $this->available_from,
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
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'default_pickup_address' => $this->default_pickup_address,
            'test_date' => $this->test_date,
            'next_focus' => $this->next_focus,
            'lifecycle' => $this->lifecycle ?: self::LIFECYCLE_ACTIVE,
            'status' => $this->resolveStatus(),
            'status_label' => self::statusLabel($this->resolveStatus()),
            'waiting_list_joined_at' => $this->waiting_list_joined_at,
            'transmission' => $this->transmission,
            'theory_status' => $this->theory_status,
            'theory_test_date' => $this->theory_test_date,
            'date_of_birth' => $this->date_of_birth,
            'age_years' => $this->ageYears(),
            'gender' => $this->gender,
            'gender_label' => self::genderLabel($this->gender),
            'available_from' => $this->available_from,
            'licence_expiry_date' => $this->licence_expiry_date,
            'archived_at' => $this->archived_at,
        ];
    }
}
