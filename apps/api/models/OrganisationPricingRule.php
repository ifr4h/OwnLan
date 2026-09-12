<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Org-level pricing variation (evening / weekend etc).
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $label
 * @property bool $active
 * @property string $days_of_week Comma-separated Mon=1..Sun=7 (ISO)
 * @property string|null $time_after HH:MM inclusive lower bound
 * @property string|null $time_before HH:MM exclusive upper bound
 * @property string $adjustment_kind add_pence|percent|set_pence
 * @property int $adjustment_value
 * @property int|null $service_id
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class OrganisationPricingRule extends ActiveRecord
{
    public const ADJUST_ADD = 'add_pence';
    public const ADJUST_PERCENT = 'percent';
    public const ADJUST_SET = 'set_pence';

    public static function tableName(): string
    {
        return '{{%organisation_pricing_rules}}';
    }
}
