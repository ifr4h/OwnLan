<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Pupil-specific service price.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property int|null $service_id
 * @property int $price_pence
 * @property string $effective_from
 * @property string|null $effective_to
 * @property string|null $note
 * @property string $created_at
 * @property string $updated_at
 */
class LearnerServiceRate extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%learner_service_rates}}';
    }
}
