<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Sellable service in the organisation catalogue.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property string $kind
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $price_pence
 * @property string $status
 * @property bool $visibility_public
 * @property string $booking_access
 * @property bool $is_default
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class OrganisationService extends ActiveRecord
{
    public const KIND_LESSON = 'lesson';
    public const KIND_MOCK_TEST = 'mock_test';
    public const KIND_REFRESHER = 'refresher';
    public const KIND_MOTORWAY = 'motorway';
    public const KIND_TEST_DAY = 'test_day';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const BOOKING_INSTRUCTOR = 'instructor_only';
    public const BOOKING_REQUEST = 'request';
    public const BOOKING_INSTANT = 'instant';

    public static function tableName(): string
    {
        return '{{%organisation_services}}';
    }

    /** @return list<string> */
    public static function kinds(): array
    {
        return [
            self::KIND_LESSON,
            self::KIND_MOCK_TEST,
            self::KIND_REFRESHER,
            self::KIND_MOTORWAY,
            self::KIND_TEST_DAY,
        ];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    /** @return list<string> */
    public static function bookingAccesses(): array
    {
        return [self::BOOKING_INSTRUCTOR, self::BOOKING_REQUEST, self::BOOKING_INSTANT];
    }
}
