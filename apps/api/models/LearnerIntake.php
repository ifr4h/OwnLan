<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Secure pupil intake invite + submission (pre-account).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int|null $created_by_instructor_id
 * @property string $invite_token_hash
 * @property string $invite_expires_at
 * @property string|null $revoked_at
 * @property string|null $prefill_first_name
 * @property string|null $prefill_last_name
 * @property string|null $prefill_mobile
 * @property string|null $prefill_email
 * @property string $status
 * @property string|null $answers_json
 * @property string|null $submitted_at
 * @property string|null $terms_acknowledged_at
 * @property int|null $learner_id
 * @property string|null $reviewed_at
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Organisation $organisation
 * @property-read Learner|null $learner
 */
class LearnerIntake extends ActiveRecord
{
    public const STATUS_OPEN = 'open';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_DISCARDED = 'discarded';

    public const INVITE_TTL_DAYS = 14;

    public static function tableName(): string
    {
        return '{{%learner_intakes}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'invite_token_hash', 'invite_expires_at', 'status', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'created_by_instructor_id', 'learner_id'], 'integer'],
            [['invite_token_hash'], 'string', 'max' => 64],
            [['prefill_first_name', 'prefill_last_name'], 'string', 'max' => 100],
            [['prefill_mobile'], 'string', 'max' => 32],
            [['prefill_email'], 'email'],
            [['status'], 'in', 'range' => [
                self::STATUS_OPEN,
                self::STATUS_SUBMITTED,
                self::STATUS_ACCEPTED,
                self::STATUS_WAITING,
                self::STATUS_DISCARDED,
            ]],
            [['answers_json'], 'string'],
            [[
                'invite_expires_at',
                'revoked_at',
                'submitted_at',
                'terms_acknowledged_at',
                'reviewed_at',
                'created_at',
                'updated_at',
            ], 'safe'],
        ];
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function getIsExpired(): bool
    {
        return $this->invite_expires_at < gmdate('Y-m-d H:i:s');
    }

    public function getIsRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function getIsOpenForLearner(): bool
    {
        return $this->status === self::STATUS_OPEN
            && !$this->isRevoked
            && !$this->isExpired;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnswers(): array
    {
        if ($this->answers_json === null || $this->answers_json === '') {
            return [];
        }
        $decoded = json_decode($this->answers_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $answers
     */
    public function setAnswers(array $answers): void
    {
        $this->answers_json = json_encode($answers, JSON_THROW_ON_ERROR);
    }
}
