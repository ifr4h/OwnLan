<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Parent, emergency or other contact for a pupil (instructor-facing).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string $kind emergency|parent|guardian|other
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $relationship
 * @property int $sort_order
 * @property string|null $notes
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Learner $learner
 */
class LearnerContact extends ActiveRecord
{
    public const KIND_EMERGENCY = 'emergency';
    public const KIND_PARENT = 'parent';
    public const KIND_GUARDIAN = 'guardian';
    public const KIND_OTHER = 'other';

    public static function tableName(): string
    {
        return '{{%learner_contacts}}';
    }

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [
            self::KIND_EMERGENCY,
            self::KIND_PARENT,
            self::KIND_GUARDIAN,
            self::KIND_OTHER,
        ];
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_EMERGENCY => 'Emergency',
            self::KIND_PARENT => 'Parent',
            self::KIND_GUARDIAN => 'Guardian',
            self::KIND_OTHER => 'Other',
            default => 'Contact',
        };
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'kind', 'name', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'learner_id', 'sort_order'], 'integer'],
            [['kind'], 'in', 'range' => self::kinds()],
            [['name'], 'string', 'max' => 120],
            [['phone'], 'string', 'max' => 32],
            [['email'], 'email'],
            [['email'], 'string', 'max' => 255, 'skipOnEmpty' => true],
            [['relationship'], 'string', 'max' => 64],
            [['notes'], 'string', 'max' => 255],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => (int) $this->id,
            'kind' => $this->kind,
            'kind_label' => self::kindLabel((string) $this->kind),
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'relationship' => $this->relationship,
            'sort_order' => (int) $this->sort_order,
            'notes' => $this->notes,
        ];
    }
}
