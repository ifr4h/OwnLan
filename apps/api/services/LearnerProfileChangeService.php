<?php

declare(strict_types=1);

namespace app\services;

use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerProfileChangeEvent;

/**
 * Records and surfaces learner-initiated profile changes for instructors.
 */
class LearnerProfileChangeService
{
    /**
     * @param list<array{field: string, from: ?string, to: ?string}> $changes
     */
    public function record(
        Learner $learner,
        string $source,
        string $summary,
        array $changes,
        string $actor = 'learner',
    ): ?LearnerProfileChangeEvent {
        $changes = array_values(array_filter(
            $changes,
            static fn (array $c): bool => ($c['from'] ?? null) !== ($c['to'] ?? null),
        ));
        if ($changes === []) {
            return null;
        }

        $event = new LearnerProfileChangeEvent();
        $event->organisation_id = (int) $learner->organisation_id;
        $event->learner_id = (int) $learner->id;
        $event->actor = $actor;
        $event->source = $source;
        $event->summary = mb_substr($summary, 0, 255);
        $event->changes_json = json_encode($changes, JSON_UNESCAPED_UNICODE) ?: '[]';
        $event->created_at = gmdate('Y-m-d H:i:s');
        $event->seen_at = null;
        $event->save(false);

        return $event;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unseenForOrganisation(int $limit = 5): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var LearnerProfileChangeEvent[] $rows */
        $rows = LearnerProfileChangeEvent::find()
            ->andWhere(['organisation_id' => $orgId, 'seen_at' => null])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit($limit)
            ->all();

        $learnerIds = array_map(static fn (LearnerProfileChangeEvent $e): int => (int) $e->learner_id, $rows);
        $names = [];
        if ($learnerIds !== []) {
            /** @var Learner[] $learners */
            $learners = Learner::find()->andWhere(['id' => $learnerIds])->all();
            foreach ($learners as $learner) {
                $names[(int) $learner->id] = $learner->fullName;
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $payload = $row->toApiArray();
            $payload['learner_name'] = $names[(int) $row->learner_id] ?? 'Pupil';
            $out[] = $payload;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentForLearner(int $learnerId, int $limit = 8): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var LearnerProfileChangeEvent[] $rows */
        $rows = LearnerProfileChangeEvent::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerId,
            ])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit($limit)
            ->all();

        return array_map(static fn (LearnerProfileChangeEvent $e) => $e->toApiArray(), $rows);
    }

    public function markSeen(int $eventId): void
    {
        $orgId = TenantContext::requireOrganisationId();
        $event = LearnerProfileChangeEvent::findOne([
            'id' => $eventId,
            'organisation_id' => $orgId,
        ]);
        if ($event === null || $event->seen_at !== null) {
            return;
        }
        $event->seen_at = gmdate('Y-m-d H:i:s');
        $event->save(false, ['seen_at']);
    }

    public function markSeenForLearner(int $learnerId): void
    {
        $orgId = TenantContext::requireOrganisationId();
        LearnerProfileChangeEvent::updateAll(
            ['seen_at' => gmdate('Y-m-d H:i:s')],
            [
                'and',
                ['organisation_id' => $orgId, 'learner_id' => $learnerId],
                ['seen_at' => null],
            ],
        );
    }
}
