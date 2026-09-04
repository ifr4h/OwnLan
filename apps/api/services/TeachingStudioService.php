<?php

declare(strict_types=1);

namespace app\services;

use app\components\TenantContext;
use app\models\Instructor;
use app\models\Lesson;
use app\models\LessonResource;
use app\models\TeachingResource;
use DateTimeImmutable;
use DateTimeZone;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Teaching Studio library — masters and lesson-specific copies.
 */
class TeachingStudioService
{
    /**
     * Built-in road board templates (geometry packs for the canvas).
     *
     * @return list<array<string, mixed>>
     */
    public function templates(): array
    {
        return [
            ['code' => 'blank', 'label' => 'Blank road', 'category' => 'basics'],
            ['code' => 't_junction', 'label' => 'T-junction', 'category' => 'junctions'],
            ['code' => 'crossroads', 'label' => 'Crossroads', 'category' => 'junctions'],
            ['code' => 'mini_roundabout', 'label' => 'Mini-roundabout', 'category' => 'roundabouts'],
            ['code' => 'roundabout', 'label' => 'Standard roundabout', 'category' => 'roundabouts'],
            ['code' => 'multi_roundabout', 'label' => 'Multi-lane roundabout', 'category' => 'roundabouts'],
            ['code' => 'traffic_lights', 'label' => 'Traffic-light junction', 'category' => 'junctions'],
            ['code' => 'dual_carriageway', 'label' => 'Dual carriageway', 'category' => 'roads'],
            ['code' => 'parking', 'label' => 'Parking area', 'category' => 'manoeuvres'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLibrary(?string $category = null, bool $favouritesOnly = false): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $q = TeachingResource::find()
            ->andWhere(['organisation_id' => $orgId, 'archived_at' => null])
            ->orderBy(['is_favourite' => SORT_DESC, 'updated_at' => SORT_DESC]);
        if ($category !== null && $category !== '') {
            $q->andWhere(['category' => $category]);
        }
        if ($favouritesOnly) {
            $q->andWhere(['is_favourite' => true]);
        }

        return array_map(fn (TeachingResource $r) => $this->serializeMaster($r, false), $q->limit(100)->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        return $this->serializeMaster($this->findMaster($id), true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $now = $this->now();
        $resource = new TeachingResource();
        $resource->organisation_id = $orgId;
        $resource->created_by_instructor_id = $this->instructorId();
        $this->applyMasterFields($resource, $data, true);
        $resource->created_at = $now;
        $resource->updated_at = $now;
        if (!$resource->save()) {
            throw new BadRequestHttpException('Could not save teaching resource.');
        }

        return $this->serializeMaster($resource, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $resource = $this->findMaster($id);
        $this->applyMasterFields($resource, $data, false);
        $resource->updated_at = $this->now();
        if (!$resource->save()) {
            throw new BadRequestHttpException('Could not update teaching resource.');
        }

        return $this->serializeMaster($resource, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function duplicate(int $id): array
    {
        $source = $this->findMaster($id);
        $now = $this->now();
        $copy = new TeachingResource();
        $copy->attributes = $source->attributes;
        $copy->id = null;
        $copy->isNewRecord = true;
        $copy->title = $source->title . ' (copy)';
        $copy->is_favourite = false;
        $copy->created_at = $now;
        $copy->updated_at = $now;
        $copy->created_by_instructor_id = $this->instructorId();
        if (!$copy->save()) {
            throw new BadRequestHttpException('Could not duplicate.');
        }

        return $this->serializeMaster($copy, true);
    }

    /**
     * @return array{status: string}
     */
    public function archive(int $id): array
    {
        $resource = $this->findMaster($id);
        $resource->archived_at = $this->now();
        $resource->updated_at = $resource->archived_at;
        $resource->save(false, ['archived_at', 'updated_at']);

        return ['status' => 'ok'];
    }

    /**
     * Snapshot master (or freeform scene) onto a lesson — does not mutate master.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function attachToLesson(int $lessonId, array $data): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $lesson = Lesson::findOne(['id' => $lessonId, 'organisation_id' => $orgId]);
        if ($lesson === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        $now = $this->now();
        $row = new LessonResource();
        $row->organisation_id = $orgId;
        $row->lesson_id = (int) $lesson->id;
        $row->learner_id = (int) $lesson->learner_id;
        $row->kind = (string) ($data['kind'] ?? TeachingResource::KIND_BOARD);
        $row->title = trim((string) ($data['title'] ?? 'Lesson explanation'));
        $row->learner_visible_note = $this->nullableText($data['learner_visible_note'] ?? null);
        $row->route_moment_id = isset($data['route_moment_id']) ? (int) $data['route_moment_id'] : null;
        $row->learner_visible = !isset($data['learner_visible']) || (bool) $data['learner_visible'];
        $row->created_at = $now;
        $row->updated_at = $now;

        if (!empty($data['source_resource_id'])) {
            $master = $this->findMaster((int) $data['source_resource_id']);
            $row->source_resource_id = (int) $master->id;
            $row->kind = $master->kind;
            if ($row->title === 'Lesson explanation') {
                $row->title = $master->title;
            }
            // Snapshot scene — further edits on lesson copy only.
            $scene = $master->scene();
            if (!empty($data['scene']) && is_array($data['scene'])) {
                $scene = $data['scene'];
            }
            $row->scene_json = json_encode($scene, JSON_THROW_ON_ERROR);
            $row->skill_codes_json = $master->skill_codes_json;
        } else {
            if (empty($data['scene']) || !is_array($data['scene'])) {
                throw new BadRequestHttpException('Scene is required.');
            }
            $row->scene_json = json_encode($data['scene'], JSON_THROW_ON_ERROR);
            if (!empty($data['skill_codes']) && is_array($data['skill_codes'])) {
                $row->skill_codes_json = json_encode(array_values($data['skill_codes']), JSON_THROW_ON_ERROR);
            }
        }

        if ($row->title === '') {
            throw new BadRequestHttpException('Title is required.');
        }
        if (!$row->save()) {
            throw new BadRequestHttpException('Could not attach to lesson.');
        }

        return $this->serializeLessonResource($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forLesson(int $lessonId): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var LessonResource[] $rows */
        $rows = LessonResource::find()
            ->andWhere(['organisation_id' => $orgId, 'lesson_id' => $lessonId])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return array_map(fn (LessonResource $r) => $this->serializeLessonResource($r), $rows);
    }

    private function findMaster(int $id): TeachingResource
    {
        $orgId = TenantContext::requireOrganisationId();
        $resource = TeachingResource::findOne([
            'id' => $id,
            'organisation_id' => $orgId,
        ]);
        if ($resource === null || $resource->archived_at !== null) {
            throw new NotFoundHttpException('Teaching resource not found.');
        }

        return $resource;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyMasterFields(TeachingResource $resource, array $data, bool $creating): void
    {
        if ($creating || array_key_exists('title', $data)) {
            $title = trim((string) ($data['title'] ?? ''));
            if ($title === '') {
                throw new BadRequestHttpException('Title is required.');
            }
            $resource->title = $title;
        }
        if ($creating || array_key_exists('kind', $data)) {
            $resource->kind = (string) ($data['kind'] ?? TeachingResource::KIND_BOARD);
        }
        if (array_key_exists('category', $data)) {
            $resource->category = $this->nullableText($data['category'] ?? null);
        }
        if (array_key_exists('description', $data)) {
            $resource->description = $this->nullableText($data['description'] ?? null);
        }
        if (array_key_exists('template_code', $data)) {
            $resource->template_code = $this->nullableText($data['template_code'] ?? null);
        }
        if (array_key_exists('is_favourite', $data)) {
            $resource->is_favourite = (bool) $data['is_favourite'];
        }
        if ($creating || array_key_exists('scene', $data)) {
            if (empty($data['scene']) || !is_array($data['scene'])) {
                if ($creating) {
                    $resource->scene_json = json_encode($this->emptyScene((string) ($data['template_code'] ?? 'blank')), JSON_THROW_ON_ERROR);
                }
            } else {
                $resource->scene_json = json_encode($data['scene'], JSON_THROW_ON_ERROR);
            }
        }
        if (array_key_exists('skill_codes', $data) && is_array($data['skill_codes'])) {
            $resource->skill_codes_json = json_encode(array_values($data['skill_codes']), JSON_THROW_ON_ERROR);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyScene(string $templateCode): array
    {
        return [
            'template' => $templateCode,
            'version' => 1,
            'objects' => [],
            'strokes' => [],
            'steps' => [
                [
                    'id' => 'step-1',
                    'label' => 'Step 1',
                    'objects' => [],
                    'strokes' => [],
                    'note' => '',
                ],
            ],
            'active_step' => 0,
            'map' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMaster(TeachingResource $r, bool $includeScene): array
    {
        $payload = [
            'id' => (int) $r->id,
            'kind' => $r->kind,
            'title' => $r->title,
            'category' => $r->category,
            'description' => $r->description,
            'template_code' => $r->template_code,
            'skill_codes' => $r->skillCodes(),
            'is_favourite' => (bool) $r->is_favourite,
            'updated_at' => $r->updated_at,
            'created_at' => $r->created_at,
        ];
        if ($includeScene) {
            $payload['scene'] = $r->scene();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLessonResource(LessonResource $r): array
    {
        try {
            $scene = json_decode($r->scene_json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $scene = [];
        }

        return [
            'id' => (int) $r->id,
            'lesson_id' => (int) $r->lesson_id,
            'source_resource_id' => $r->source_resource_id !== null ? (int) $r->source_resource_id : null,
            'kind' => $r->kind,
            'title' => $r->title,
            'scene' => is_array($scene) ? $scene : [],
            'learner_visible_note' => $r->learner_visible_note,
            'skill_codes' => $r->skillCodes(),
            'route_moment_id' => $r->route_moment_id !== null ? (int) $r->route_moment_id : null,
            'learner_visible' => (bool) $r->learner_visible,
            'created_at' => $r->created_at,
        ];
    }

    private function instructorId(): ?int
    {
        $orgId = TenantContext::organisationId();
        if ($orgId === null) {
            return null;
        }
        $instructor = Instructor::find()
            ->andWhere(['organisation_id' => $orgId])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        return $instructor !== null ? (int) $instructor->id : null;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
