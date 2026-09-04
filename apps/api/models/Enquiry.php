<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Structured learner enquiry from a public instructor profile.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int|null $instructor_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string $mobile
 * @property string $postcode
 * @property string|null $transmission
 * @property string|null $experience_band
 * @property string|null $availability_json
 * @property string|null $desired_start
 * @property string|null $theory_status
 * @property string|null $practical_test_date
 * @property string|null $message
 * @property string $source
 * @property string|null $source_tag
 * @property string|null $service_interest
 * @property string $status
 * @property string|null $decline_reason
 * @property int|null $converted_learner_id
 * @property int|null $intake_id
 * @property string|null $contacted_at
 * @property string|null $reviewed_at
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Organisation $organisation
 * @property-read Learner|null $convertedLearner
 */
class Enquiry extends ActiveRecord
{
    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CONVERTED = 'converted';

    public const SOURCE_PROFILE = 'profile';
    public const SOURCE_WAITING_LIST = 'waiting_list';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_REFERRAL = 'referral';

    public static function tableName(): string
    {
        return '{{%enquiries}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'first_name', 'last_name', 'mobile', 'postcode', 'status', 'source', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'instructor_id', 'converted_learner_id', 'intake_id'], 'integer'],
            [['first_name', 'last_name'], 'string', 'max' => 100],
            [['email'], 'email'],
            [['mobile'], 'string', 'max' => 32],
            [['postcode'], 'string', 'max' => 16],
            [['transmission', 'experience_band', 'desired_start', 'theory_status', 'source', 'source_tag', 'status'], 'string', 'max' => 64],
            [['service_interest'], 'string', 'max' => 120],
            [['message', 'decline_reason', 'availability_json'], 'string'],
            [['practical_test_date', 'contacted_at', 'reviewed_at', 'created_at', 'updated_at'], 'safe'],
            [['status'], 'in', 'range' => [
                self::STATUS_NEW,
                self::STATUS_CONTACTED,
                self::STATUS_WAITING,
                self::STATUS_ACCEPTED,
                self::STATUS_DECLINED,
                self::STATUS_CONVERTED,
            ]],
        ];
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function getConvertedLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'converted_learner_id']);
    }

    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAvailability(): array
    {
        if ($this->availability_json === null || $this->availability_json === '') {
            return [];
        }
        $decoded = json_decode($this->availability_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $availability
     */
    public function setAvailability(array $availability): void
    {
        $this->availability_json = json_encode($availability, JSON_THROW_ON_ERROR);
    }
}
