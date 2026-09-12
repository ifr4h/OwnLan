<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Learner-initiated profile / place changes for instructor attention.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string $actor
 * @property string $source
 * @property string $summary
 * @property string $changes_json
 * @property string $created_at
 * @property string|null $seen_at
 */
class LearnerProfileChangeEvent extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%learner_profile_change_events}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'actor', 'source', 'summary', 'changes_json', 'created_at'], 'required'],
            [['organisation_id', 'learner_id'], 'integer'],
            [['actor'], 'string', 'max' => 32],
            [['source'], 'string', 'max' => 64],
            [['summary'], 'string', 'max' => 255],
            [['changes_json'], 'string'],
            [['created_at', 'seen_at'], 'safe'],
        ];
    }

    /**
     * @return list<array{field: string, from: string|null, to: string|null}>
     */
    public function changes(): array
    {
        $decoded = json_decode((string) $this->changes_json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'field' => (string) ($row['field'] ?? ''),
                'from' => isset($row['from']) ? (string) $row['from'] : null,
                'to' => isset($row['to']) ? (string) $row['to'] : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => (int) $this->id,
            'learner_id' => (int) $this->learner_id,
            'actor' => $this->actor,
            'source' => $this->source,
            'summary' => $this->summary,
            'changes' => $this->changes(),
            'created_at' => $this->created_at,
            'seen_at' => $this->seen_at,
        ];
    }
}
