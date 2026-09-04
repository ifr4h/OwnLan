<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Business mileage log entry.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $vehicle_id
 * @property string $logged_on Y-m-d
 * @property string $distance_miles
 * @property string $purpose business|personal
 * @property int|null $start_reading
 * @property int|null $end_reading
 * @property string|null $notes
 * @property int|null $created_by_user_id
 * @property string $created_at
 * @property string $updated_at
 */
class MileageLog extends ActiveRecord
{
    public const PURPOSE_BUSINESS = 'business';
    public const PURPOSE_PERSONAL = 'personal';

    public static function tableName(): string
    {
        return '{{%mileage_logs}}';
    }

    /**
     * @return list<string>
     */
    public static function purposes(): array
    {
        return [self::PURPOSE_BUSINESS, self::PURPOSE_PERSONAL];
    }

    public function rules(): array
    {
        return [
            [[
                'organisation_id',
                'vehicle_id',
                'logged_on',
                'distance_miles',
                'purpose',
                'created_at',
                'updated_at',
            ], 'required'],
            [['organisation_id', 'vehicle_id', 'start_reading', 'end_reading', 'created_by_user_id'], 'integer'],
            [['distance_miles'], 'number', 'min' => 0.1],
            [['purpose'], 'in', 'range' => self::purposes()],
            [['logged_on'], 'date', 'format' => 'php:Y-m-d'],
            [['notes'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function getVehicle(): ActiveQuery
    {
        return $this->hasOne(Vehicle::class, ['id' => 'vehicle_id']);
    }

    public function purposeLabel(): string
    {
        return match ($this->purpose) {
            self::PURPOSE_PERSONAL => 'Personal',
            default => 'Business',
        };
    }
}
