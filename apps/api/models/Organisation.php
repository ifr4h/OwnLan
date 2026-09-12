<?php

declare(strict_types=1);

namespace app\models;

use DateTimeImmutable;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $timezone
 * @property int|null $default_hourly_rate_pence
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property int $default_lesson_duration_minutes
 * @property string|null $service_area
 * @property string|null $cancellation_policy
 * @property string $work_days JSON array of ISO weekdays 1=Mon … 7=Sun
 * @property string $work_start_time HH:MM
 * @property string $work_end_time HH:MM
 * @property int $week_starts_on ISO weekday 1=Mon … 7=Sun for diary week layout
 * @property string $booking_mode manual|request|instant
 * @property string $learner_reschedule_mode manual|request|instant
 * @property bool $learner_can_cancel
 * @property int $cancellation_notice_hours
 * @property string $cancellation_late_policy charge|decide
 * @property int $booking_minimum_notice_hours
 * @property int $booking_advance_weeks
 * @property int $booking_slot_increment_minutes
 * @property string|null $booking_allowed_durations JSON array of minutes
 * @property string|null $calendar_feed_token
 * @property string|null $calendar_feed_created_at
 * @property string $calendar_privacy_mode full|private
 * @property string|null $profile_slug
 * @property string|null $profile_status
 * @property string|null $profile_acquisition_mode
 * @property string|null $profile_intro
 * @property string|null $profile_photo_path
 * @property string|null $profile_transmission
 * @property string|null $profile_teaching_areas
 * @property string|null $profile_languages
 * @property string|null $profile_adi_status
 * @property int|null $profile_years_teaching
 * @property string|null $profile_vehicle_summary
 * @property string|null $profile_public_pricing
 * @property bool $profile_allow_waiting_list
 * @property string|null $profile_business_name
 * @property string|null $profile_accent_colour
 * @property string|null $profile_cover_path
 * @property string|null $profile_teaching_styles
 * @property string|null $profile_services
 * @property string|null $profile_faqs
 * @property string|null $profile_social_links
 * @property string|null $profile_contact_phone
 * @property string|null $profile_contact_email
 * @property string|null $profile_whatsapp
 * @property bool $profile_show_phone
 * @property bool $profile_show_email
 * @property bool $profile_dual_controls
 * @property bool $profile_allow_indexing
 * @property string $profile_teaches_gender
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Membership[] $memberships
 * @property-read Instructor[] $instructors
 */
class Organisation extends ActiveRecord
{
    public const DEFAULT_TIMEZONE = 'Europe/London';
    public const DEFAULT_DURATION_MINUTES = 60;
    public const DEFAULT_HOURLY_RATE_PENCE = 3500;
    public const DEFAULT_WORK_DAYS = [1, 2, 3, 4, 5, 6];
    public const DEFAULT_WORK_START = '09:00';
    public const DEFAULT_WORK_END = '18:00';
    public const DEFAULT_WEEK_STARTS_ON = 1; // Monday

    public const BOOKING_MODE_MANUAL = 'manual';
    public const BOOKING_MODE_REQUEST = 'request';
    public const BOOKING_MODE_INSTANT = 'instant';

    public const DEFAULT_BOOKING_MODE = self::BOOKING_MODE_MANUAL;
    public const DEFAULT_MINIMUM_NOTICE_HOURS = 12;
    public const DEFAULT_BOOKING_ADVANCE_WEEKS = 4;
    public const DEFAULT_SLOT_INCREMENT_MINUTES = 30;
    public const DEFAULT_CANCELLATION_NOTICE_HOURS = 48;

    public const CANCELLATION_LATE_CHARGE = 'charge';
    public const CANCELLATION_LATE_DECIDE = 'decide';
    public const DEFAULT_CANCELLATION_LATE_POLICY = self::CANCELLATION_LATE_DECIDE;

    public const TEACHES_GENDER_ANY = 'any';
    public const TEACHES_GENDER_FEMALE = 'female';
    public const TEACHES_GENDER_MALE = 'male';

    public static function tableName(): string
    {
        return '{{%organisations}}';
    }

    /**
     * @return list<string>
     */
    public static function teachesGenderValues(): array
    {
        return [
            self::TEACHES_GENDER_ANY,
            self::TEACHES_GENDER_FEMALE,
            self::TEACHES_GENDER_MALE,
        ];
    }

    public static function teachesGenderLabel(?string $value): string
    {
        return match ($value) {
            self::TEACHES_GENDER_FEMALE => 'Women only',
            self::TEACHES_GENDER_MALE => 'Men only',
            default => 'Everyone',
        };
    }

    public function rules(): array
    {
        return [
            [['name', 'timezone', 'created_at', 'updated_at'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['timezone'], 'string', 'max' => 64],
            [['contact_phone'], 'string', 'max' => 32],
            [['contact_email'], 'email'],
            [['contact_email'], 'string', 'max' => 255],
            [['service_area', 'cancellation_policy'], 'string'],
            [['default_hourly_rate_pence'], 'integer', 'min' => 0],
            [['default_lesson_duration_minutes'], 'integer', 'min' => 15, 'max' => 480],
            [['work_days'], 'string', 'max' => 64],
            [['work_start_time', 'work_end_time'], 'string', 'max' => 5],
            [['work_start_time', 'work_end_time'], 'match', 'pattern' => '/^\d{2}:\d{2}$/'],
            [['week_starts_on'], 'integer', 'min' => 1, 'max' => 7],
            [['booking_mode', 'learner_reschedule_mode'], 'string', 'max' => 16],
            [['booking_mode'], 'in', 'range' => [
                self::BOOKING_MODE_MANUAL,
                self::BOOKING_MODE_REQUEST,
                self::BOOKING_MODE_INSTANT,
            ]],
            [['learner_reschedule_mode'], 'in', 'range' => [
                self::BOOKING_MODE_MANUAL,
                self::BOOKING_MODE_REQUEST,
                self::BOOKING_MODE_INSTANT,
            ]],
            [['learner_can_cancel'], 'boolean'],
            [['cancellation_notice_hours'], 'integer', 'min' => 0, 'max' => 168],
            [['cancellation_late_policy'], 'in', 'range' => [
                self::CANCELLATION_LATE_CHARGE,
                self::CANCELLATION_LATE_DECIDE,
            ]],
            [['booking_minimum_notice_hours'], 'integer', 'min' => 0, 'max' => 168],
            [['booking_advance_weeks'], 'integer', 'min' => 1, 'max' => 52],
            [['booking_slot_increment_minutes'], 'integer', 'min' => 5, 'max' => 60],
            [['booking_allowed_durations'], 'string', 'max' => 128],
        ];
    }

    public function getMemberships(): ActiveQuery
    {
        return $this->hasMany(Membership::class, ['organisation_id' => 'id']);
    }

    public function getInstructors(): ActiveQuery
    {
        return $this->hasMany(Instructor::class, ['organisation_id' => 'id']);
    }

    public function defaultLessonDurationMinutes(): int
    {
        $minutes = (int) ($this->default_lesson_duration_minutes ?: self::DEFAULT_DURATION_MINUTES);
        if ($minutes < 15 || $minutes > 480) {
            return self::DEFAULT_DURATION_MINUTES;
        }

        return $minutes;
    }

    /**
     * @return list<int>
     */
    public function workDays(): array
    {
        $raw = trim((string) $this->work_days);
        if ($raw === '') {
            return self::DEFAULT_WORK_DAYS;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::DEFAULT_WORK_DAYS;
        }
        $days = [];
        foreach ($decoded as $day) {
            $n = (int) $day;
            if ($n >= 1 && $n <= 7) {
                $days[] = $n;
            }
        }
        $days = array_values(array_unique($days));
        sort($days);

        return $days !== [] ? $days : self::DEFAULT_WORK_DAYS;
    }

    public function workStartTime(): string
    {
        $t = trim((string) $this->work_start_time);

        return preg_match('/^\d{2}:\d{2}$/', $t) === 1 ? $t : self::DEFAULT_WORK_START;
    }

    public function workEndTime(): string
    {
        $t = trim((string) $this->work_end_time);

        return preg_match('/^\d{2}:\d{2}$/', $t) === 1 ? $t : self::DEFAULT_WORK_END;
    }

    /**
     * ISO weekday the diary week starts on (1=Monday … 7=Sunday).
     */
    public function weekStartsOn(): int
    {
        $n = (int) ($this->week_starts_on ?? self::DEFAULT_WEEK_STARTS_ON);
        if ($n < 1 || $n > 7) {
            return self::DEFAULT_WEEK_STARTS_ON;
        }

        return $n;
    }

    public function isWorkingDay(int $isoWeekday): bool
    {
        return in_array($isoWeekday, $this->workDays(), true);
    }

    /**
     * Whether a local lesson start+duration sits inside configured working hours.
     */
    public function isWithinWorkingHours(DateTimeImmutable $localStart, int $durationMinutes): bool
    {
        if (!$this->isWorkingDay((int) $localStart->format('N'))) {
            return false;
        }
        $startMin = ((int) $localStart->format('H')) * 60 + (int) $localStart->format('i');
        $endMin = $startMin + max(0, $durationMinutes);
        [$wsH, $wsM] = array_map('intval', explode(':', $this->workStartTime()));
        [$weH, $weM] = array_map('intval', explode(':', $this->workEndTime()));
        $workStart = $wsH * 60 + $wsM;
        $workEnd = $weH * 60 + $weM;
        if ($workEnd <= $workStart) {
            return true;
        }

        return $startMin >= $workStart && $endMin <= $workEnd;
    }

    public function bookingMode(): string
    {
        $mode = trim((string) ($this->booking_mode ?? ''));
        if (!in_array($mode, [
            self::BOOKING_MODE_MANUAL,
            self::BOOKING_MODE_REQUEST,
            self::BOOKING_MODE_INSTANT,
        ], true)) {
            return self::DEFAULT_BOOKING_MODE;
        }

        return $mode;
    }

    public function learnerRescheduleMode(): string
    {
        $mode = trim((string) ($this->learner_reschedule_mode ?? ''));
        if (!in_array($mode, [
            self::BOOKING_MODE_MANUAL,
            self::BOOKING_MODE_REQUEST,
            self::BOOKING_MODE_INSTANT,
        ], true)) {
            return self::DEFAULT_BOOKING_MODE;
        }

        return $mode;
    }

    public function learnerCanCancel(): bool
    {
        return (bool) ($this->learner_can_cancel ?? true);
    }

    public function cancellationNoticeHours(): int
    {
        $hours = (int) ($this->cancellation_notice_hours ?? self::DEFAULT_CANCELLATION_NOTICE_HOURS);

        return max(0, min(168, $hours));
    }

    public function cancellationLatePolicy(): string
    {
        $policy = trim((string) ($this->cancellation_late_policy ?? ''));
        if (!in_array($policy, [
            self::CANCELLATION_LATE_CHARGE,
            self::CANCELLATION_LATE_DECIDE,
        ], true)) {
            return self::DEFAULT_CANCELLATION_LATE_POLICY;
        }

        return $policy;
    }

    public function bookingMinimumNoticeHours(): int
    {
        $hours = (int) ($this->booking_minimum_notice_hours ?? self::DEFAULT_MINIMUM_NOTICE_HOURS);

        return max(0, min(168, $hours));
    }

    public function bookingAdvanceWeeks(): int
    {
        $weeks = (int) ($this->booking_advance_weeks ?? self::DEFAULT_BOOKING_ADVANCE_WEEKS);

        return max(1, min(52, $weeks));
    }

    public function bookingSlotIncrementMinutes(): int
    {
        $mins = (int) ($this->booking_slot_increment_minutes ?? self::DEFAULT_SLOT_INCREMENT_MINUTES);
        if ($mins < 5 || $mins > 60) {
            return self::DEFAULT_SLOT_INCREMENT_MINUTES;
        }

        return $mins;
    }

    /**
     * @return list<int>
     */
    public function bookingAllowedDurations(): array
    {
        $raw = trim((string) ($this->booking_allowed_durations ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $durations = [];
        foreach ($decoded as $value) {
            $n = (int) $value;
            if ($n >= 15 && $n <= 480) {
                $durations[] = $n;
            }
        }
        $durations = array_values(array_unique($durations));
        sort($durations);

        return $durations;
    }
}
