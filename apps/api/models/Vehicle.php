<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Instructor teaching vehicle.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $registration
 * @property string|null $make
 * @property string|null $model
 * @property string $transmission manual|automatic
 * @property bool $is_primary
 * @property bool $is_active
 * @property string|null $notes
 * @property string $created_at
 * @property string $updated_at
 */
class Vehicle extends ActiveRecord
{
    public const TRANSMISSION_MANUAL = 'manual';
    public const TRANSMISSION_AUTOMATIC = 'automatic';

    public static function tableName(): string
    {
        return '{{%vehicles}}';
    }

    /**
     * @return list<string>
     */
    public static function transmissions(): array
    {
        return [self::TRANSMISSION_MANUAL, self::TRANSMISSION_AUTOMATIC];
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'registration', 'created_at', 'updated_at'], 'required'],
            [['organisation_id'], 'integer'],
            [['is_primary', 'is_active'], 'boolean'],
            [['registration'], 'string', 'max' => 16],
            [['make', 'model'], 'string', 'max' => 64],
            [['transmission'], 'in', 'range' => self::transmissions()],
            [['notes'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function displayName(): string
    {
        $parts = array_filter([
            trim((string) ($this->make ?? '')),
            trim((string) ($this->model ?? '')),
        ]);

        return $parts !== [] ? implode(' ', $parts) : 'Vehicle';
    }

    public function transmissionLabel(): string
    {
        return match ($this->transmission) {
            self::TRANSMISSION_AUTOMATIC => 'Automatic',
            default => 'Manual',
        };
    }
}
