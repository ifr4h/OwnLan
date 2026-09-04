<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Lightweight optional pupil availability window.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int $weekday
 * @property string $mode
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string $created_at
 * @property string $updated_at
 */
class LearnerAvailability extends ActiveRecord
{
    public const MODE_FLEXIBLE = 'flexible';
    public const MODE_AFTER = 'after';
    public const MODE_BEFORE = 'before';
    public const MODE_BETWEEN = 'between';

    public static function tableName(): string
    {
        return '{{%learner_availability}}';
    }

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return [
            self::MODE_FLEXIBLE,
            self::MODE_AFTER,
            self::MODE_BEFORE,
            self::MODE_BETWEEN,
        ];
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'weekday', 'mode', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'learner_id', 'weekday'], 'integer'],
            [['weekday'], 'integer', 'min' => 1, 'max' => 7],
            [['mode'], 'in', 'range' => self::modes()],
            [['start_time', 'end_time'], 'string', 'max' => 5],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => (int) $this->id,
            'weekday' => (int) $this->weekday,
            'weekday_label' => $this->weekdayLabel(),
            'mode' => $this->mode,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'label' => $this->displayLabel(),
        ];
    }

    public function weekdayLabel(): string
    {
        return match ((int) $this->weekday) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            default => 'Sunday',
        };
    }

    public function displayLabel(): string
    {
        return match ($this->mode) {
            self::MODE_FLEXIBLE => 'Flexible',
            self::MODE_AFTER => 'After ' . $this->formatTime($this->start_time),
            self::MODE_BEFORE => 'Before ' . $this->formatTime($this->end_time),
            self::MODE_BETWEEN => $this->formatTime($this->start_time) . '–' . $this->formatTime($this->end_time),
            default => $this->mode,
        };
    }

    private function formatTime(?string $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        return substr($time, 0, 5);
    }
}
