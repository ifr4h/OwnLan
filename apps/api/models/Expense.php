<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Instructor business spending (fuel, insurance, etc.).
 * Not pupil payment truth — payments and lesson charges stay in their own tables.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $amount_pence
 * @property string $category
 * @property string|null $supplier
 * @property string|null $payment_method
 * @property int|null $vehicle_id
 * @property string|null $receipt_path
 * @property string|null $receipt_original_name
 * @property string $spent_on Y-m-d
 * @property string|null $notes
 * @property string|null $voided_at
 * @property string|null $void_reason
 * @property int|null $created_by_user_id
 * @property string $created_at
 * @property string $updated_at
 */
class Expense extends ActiveRecord
{
    public const CATEGORY_FUEL = 'fuel';
    public const CATEGORY_INSURANCE = 'insurance';
    public const CATEGORY_VEHICLE_REPAIRS = 'vehicle_repairs';
    public const CATEGORY_SERVICING = 'servicing';
    public const CATEGORY_TYRES = 'tyres';
    public const CATEGORY_PARKING = 'parking';
    public const CATEGORY_TOLLS = 'tolls';
    public const CATEGORY_FRANCHISE = 'franchise';
    public const CATEGORY_TRAINING = 'training';
    public const CATEGORY_PHONE = 'phone';
    public const CATEGORY_SOFTWARE = 'software';
    public const CATEGORY_ACCOUNTANCY = 'accountancy';
    public const CATEGORY_ADS = 'advertising';
    public const CATEGORY_OFFICE = 'office';
    public const CATEGORY_OTHER = 'other';

    /** Categories where a vehicle link is typically relevant. */
    public const VEHICLE_CATEGORIES = [
        self::CATEGORY_FUEL,
        self::CATEGORY_INSURANCE,
        self::CATEGORY_VEHICLE_REPAIRS,
        self::CATEGORY_SERVICING,
        self::CATEGORY_TYRES,
        self::CATEGORY_PARKING,
        self::CATEGORY_TOLLS,
    ];

    public static function tableName(): string
    {
        return '{{%expenses}}';
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_FUEL,
            self::CATEGORY_INSURANCE,
            self::CATEGORY_VEHICLE_REPAIRS,
            self::CATEGORY_SERVICING,
            self::CATEGORY_TYRES,
            self::CATEGORY_PARKING,
            self::CATEGORY_TOLLS,
            self::CATEGORY_FRANCHISE,
            self::CATEGORY_TRAINING,
            self::CATEGORY_PHONE,
            self::CATEGORY_SOFTWARE,
            self::CATEGORY_ACCOUNTANCY,
            self::CATEGORY_ADS,
            self::CATEGORY_OFFICE,
            self::CATEGORY_OTHER,
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            self::CATEGORY_FUEL => 'Fuel',
            self::CATEGORY_INSURANCE => 'Insurance',
            self::CATEGORY_VEHICLE_REPAIRS => 'Vehicle repairs',
            self::CATEGORY_SERVICING => 'Servicing',
            self::CATEGORY_TYRES => 'Tyres',
            self::CATEGORY_PARKING => 'Parking',
            self::CATEGORY_TOLLS => 'Tolls',
            self::CATEGORY_FRANCHISE => 'Franchise fees',
            self::CATEGORY_TRAINING => 'Training / CPD',
            self::CATEGORY_PHONE => 'Phone',
            self::CATEGORY_SOFTWARE => 'Software',
            self::CATEGORY_ACCOUNTANCY => 'Accountancy',
            self::CATEGORY_ADS => 'Advertising',
            self::CATEGORY_OFFICE => 'Office / stationery',
            self::CATEGORY_OTHER => 'Other',
            default => $category,
        };
    }

    public static function categoryAcceptsVehicle(string $category): bool
    {
        return in_array($category, self::VEHICLE_CATEGORIES, true);
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'amount_pence', 'category', 'spent_on', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'amount_pence', 'created_by_user_id', 'vehicle_id'], 'integer'],
            [['amount_pence'], 'integer', 'min' => 1],
            [['category'], 'in', 'range' => self::categories()],
            [['supplier'], 'string', 'max' => 120],
            [['payment_method'], 'string', 'max' => 32],
            [['receipt_path', 'receipt_original_name'], 'string', 'max' => 255],
            [['spent_on'], 'date', 'format' => 'php:Y-m-d'],
            [['notes', 'void_reason'], 'string'],
            [['voided_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getVehicle(): ActiveQuery
    {
        return $this->hasOne(Vehicle::class, ['id' => 'vehicle_id']);
    }
}
