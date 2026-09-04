<?php

declare(strict_types=1);

namespace app\services;

use app\components\PortalContext;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\LessonRoute;
use app\models\RouteMoment;
use DateTimeImmutable;
use DateTimeZone;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * In-drive / post-drive route moments — one-tap mark, enrich when parked.
 */
class RouteMomentService
{
    /**
     * One-tap mark during / after recording. Minimal fields only.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function mark(int $lessonId, array $data): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $lesson = Lesson::findOne(['id' => $lessonId, 'organisation_id' => $orgId]);
        if ($lesson === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        $route = LessonRoute::findOne([
            'lesson_id' => $lessonId,
            'organisation_id' => $orgId,
            'deleted_at' => null,
        ]);
        if ($route === null) {
            throw new BadRequestHttpException('Start route recording before marking a moment.');
        }

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new BadRequestHttpException('A GPS position is required to mark a moment.');
        }

        $now = $this->now();
        $recordedAt = $now;
        if (!empty($data['recorded_at']) && is_string($data['recorded_at'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['recorded_at'], new DateTimeZone('UTC'));
            if ($parsed !== false) {
                $recordedAt = $parsed->format('Y-m-d H:i:s');
            }
        }

        $offset = null;
        if ($route->started_at) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $route->started_at, new DateTimeZone('UTC'));
            $at = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $recordedAt, new DateTimeZone('UTC'));
            if ($start && $at) {
                $offset = max(0, $at->getTimestamp() - $start->getTimestamp());
            }
        }

        $moment = new RouteMoment();
        $moment->organisation_id = $orgId;
        $moment->lesson_route_id = (int) $route->id;
        $moment->lesson_id = (int) $lesson->id;
        $moment->learner_id = (int) $lesson->learner_id;
        $moment->recorded_at = $recordedAt;
        $moment->offset_seconds = $offset;
        $moment->lat = $lat;
        $moment->lng = $lng;
        $moment->kind = RouteMoment::KIND_REVIEW;
        $moment->label = null;
        $moment->learner_note = null;
        $moment->learner_visible = false;
        $moment->created_at = $now;
        $moment->updated_at = $now;
        if (!$moment->save()) {
            throw new BadRequestHttpException('Could not save moment.');
        }

        return $this->serialize($moment);
    }

    /**
     * Enrich when safely parked — kind, label, learner note, visibility.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $momentId, array $data): array
    {
        $moment = $this->findOwned($momentId);
        if (array_key_exists('kind', $data) && in_array($data['kind'], RouteMoment::KINDS, true)) {
            $moment->kind = (string) $data['kind'];
        }
        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);
            $moment->label = $label === '' ? null : mb_substr($label, 0, 200);
        }
        if (array_key_exists('learner_note', $data)) {
            $note = trim((string) $data['learner_note']);
            $moment->learner_note = $note === '' ? null : $note;
        }
        if (array_key_exists('learner_visible', $data)) {
            $moment->learner_visible = (bool) $data['learner_visible'];
        }
        $moment->updated_at = $this->now();
        if (!$moment->save()) {
            throw new BadRequestHttpException('Could not update moment.');
        }

        return $this->serialize($moment);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forLesson(int $lessonId): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var RouteMoment[] $rows */
        $rows = RouteMoment::find()
            ->andWhere(['organisation_id' => $orgId, 'lesson_id' => $lessonId])
            ->orderBy(['offset_seconds' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(fn (RouteMoment $m) => $this->serialize($m), $rows);
    }

    /**
     * Learner-visible moments for a shared route.
     *
     * @return list<array<string, mixed>>
     */
    public function forPortalRoute(int $routeId): array
    {
        $account = PortalContext::requireAccount();
        $route = LessonRoute::findOne([
            'id' => $routeId,
            'organisation_id' => (int) $account->organisation_id,
            'learner_id' => (int) $account->learner_id,
            'learner_visible' => true,
            'deleted_at' => null,
        ]);
        if ($route === null) {
            throw new NotFoundHttpException('Route not found.');
        }

        /** @var RouteMoment[] $rows */
        $rows = RouteMoment::find()
            ->andWhere([
                'lesson_route_id' => (int) $route->id,
                'learner_visible' => true,
            ])
            ->orderBy(['offset_seconds' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(fn (RouteMoment $m) => $this->serializeLearner($m), $rows);
    }

    /**
     * When a route is shared, optionally publish all moments that have notes.
     */
    public function publishForSharedRoute(LessonRoute $route): void
    {
        RouteMoment::updateAll(
            ['learner_visible' => true, 'updated_at' => $this->now()],
            [
                'and',
                ['lesson_route_id' => (int) $route->id],
                ['not', ['learner_note' => null]],
                ['!=', 'learner_note', ''],
            ],
        );
    }

    private function findOwned(int $id): RouteMoment
    {
        $orgId = TenantContext::requireOrganisationId();
        $moment = RouteMoment::findOne(['id' => $id, 'organisation_id' => $orgId]);
        if ($moment === null) {
            throw new NotFoundHttpException('Moment not found.');
        }

        return $moment;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(RouteMoment $m): array
    {
        return [
            'id' => (int) $m->id,
            'lesson_id' => (int) $m->lesson_id,
            'lesson_route_id' => (int) $m->lesson_route_id,
            'recorded_at' => $m->recorded_at,
            'offset_seconds' => $m->offset_seconds !== null ? (int) $m->offset_seconds : null,
            'offset_label' => $this->offsetLabel($m->offset_seconds),
            'lat' => (float) $m->lat,
            'lng' => (float) $m->lng,
            'kind' => $m->kind,
            'label' => $m->label,
            'learner_note' => $m->learner_note,
            'learner_visible' => (bool) $m->learner_visible,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLearner(RouteMoment $m): array
    {
        return [
            'id' => (int) $m->id,
            'offset_seconds' => $m->offset_seconds !== null ? (int) $m->offset_seconds : null,
            'offset_label' => $this->offsetLabel($m->offset_seconds),
            'lat' => (float) $m->lat,
            'lng' => (float) $m->lng,
            'kind' => $m->kind,
            'label' => $m->label,
            'learner_note' => $m->learner_note,
        ];
    }

    private function offsetLabel(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;

        return sprintf('%d:%02d', $m, $s);
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
