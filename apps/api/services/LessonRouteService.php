<?php

declare(strict_types=1);

namespace app\services;

use app\components\EncodedPolyline;
use app\components\PortalContext;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\LessonRoute;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Explicit instructor-started lesson route recording.
 * Never silent tracking; learner visibility is opt-in after completion.
 */
class LessonRouteService
{
    /** Soft retention: completed routes older than this may be purged by ops job later. */
    public const RETENTION_DAYS = 730;

    private ProgressService $progress;

    public function __construct(?ProgressService $progress = null)
    {
        $this->progress = $progress ?? new ProgressService();
    }

    /**
     * @return array<string, mixed>
     */
    public function start(int $lessonId): array
    {
        $lesson = $this->requireInstructorLesson($lessonId);
        $existing = LessonRoute::findOne([
            'lesson_id' => (int) $lesson->id,
            'organisation_id' => (int) $lesson->organisation_id,
        ]);

        if ($existing !== null && $existing->deleted_at === null) {
            if ($existing->status === LessonRoute::STATUS_RECORDING) {
                return $this->serializeInstructor($existing);
            }
            if ($existing->status === LessonRoute::STATUS_COMPLETED) {
                throw new ConflictHttpException('A route is already recorded for this lesson. Delete it before recording again.');
            }
        }

        $now = $this->nowUtc();
        $route = $existing ?? new LessonRoute();
        $route->organisation_id = (int) $lesson->organisation_id;
        $route->lesson_id = (int) $lesson->id;
        $route->learner_id = (int) $lesson->learner_id;
        $route->status = LessonRoute::STATUS_RECORDING;
        $route->started_at = $now;
        $route->ended_at = null;
        $route->duration_seconds = null;
        $route->distance_metres = null;
        $route->point_count = 0;
        $route->encoded_polyline = null;
        $route->bounds_json = null;
        $route->label = null;
        $route->learner_visible = false;
        $route->shared_at = null;
        $route->deleted_at = null;
        $route->created_at = $route->created_at ?: $now;
        $route->updated_at = $now;

        if (!$route->save()) {
            throw new BadRequestHttpException('Could not start route recording.');
        }

        return $this->serializeInstructor($route);
    }

    /**
     * Finalise a recording with a sampled point list from the instructor device.
     *
     * Expected body:
     * - points: [{lat, lng, accuracy?, recorded_at?}, ...]  (client-thinned, ~5–10s)
     * - ended_at?: ISO/local optional
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function stop(int $lessonId, array $data): array
    {
        $lesson = $this->requireInstructorLesson($lessonId);
        $route = $this->requireRouteForLesson((int) $lesson->id, (int) $lesson->organisation_id);

        if ($route->status !== LessonRoute::STATUS_RECORDING) {
            throw new ConflictHttpException('Route recording is not active.');
        }

        $rawPoints = $data['points'] ?? null;
        if (!is_array($rawPoints) || $rawPoints === []) {
            throw new BadRequestHttpException('Record at least a few GPS points before stopping.');
        }

        $points = $this->normalizePoints($rawPoints);
        if (count($points) < 2) {
            throw new BadRequestHttpException('Need at least two usable GPS points.');
        }

        // Cap geometry size — ~2 hours at 8s ≈ 900 points; refuse pathological payloads.
        if (count($points) > 2500) {
            throw new BadRequestHttpException('Route has too many points. Sample less frequently.');
        }

        $nowDt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $nowDt->format('Y-m-d H:i:s');
        $started = $route->started_at
            ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $route->started_at, new DateTimeZone('UTC'))
            : false;
        $ended = $nowDt;
        if (!empty($data['ended_at']) && is_string($data['ended_at'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['ended_at'], new DateTimeZone('UTC'));
            if ($parsed !== false) {
                $ended = $parsed;
            }
        }

        $duration = $started instanceof DateTimeImmutable
            ? max(0, $ended->getTimestamp() - $started->getTimestamp())
            : null;

        $bounds = EncodedPolyline::bounds($points);
        $route->status = LessonRoute::STATUS_COMPLETED;
        $route->ended_at = $ended->format('Y-m-d H:i:s');
        $route->duration_seconds = $duration;
        $route->distance_metres = EncodedPolyline::approximateDistanceMetres($points);
        $route->point_count = count($points);
        $route->encoded_polyline = EncodedPolyline::encode($points);
        $route->bounds_json = $bounds !== null ? json_encode($bounds, JSON_THROW_ON_ERROR) : null;
        $route->updated_at = $now;

        if (!$route->save()) {
            throw new BadRequestHttpException('Could not save route.');
        }

        return $this->serializeInstructor($route);
    }

    /**
     * @return array<string, mixed>
     */
    public function shareWithLearner(int $lessonId): array
    {
        $lesson = $this->requireInstructorLesson($lessonId);
        $route = $this->requireRouteForLesson((int) $lesson->id, (int) $lesson->organisation_id);

        if ($route->status !== LessonRoute::STATUS_COMPLETED || $route->deleted_at !== null) {
            throw new BadRequestHttpException('Only a completed route can be shared.');
        }

        $now = $this->nowUtc();
        $route->learner_visible = true;
        $route->shared_at = $now;
        $route->updated_at = $now;
        $route->save(false, ['learner_visible', 'shared_at', 'updated_at']);

        (new RouteMomentService())->publishForSharedRoute($route);

        return $this->serializeInstructor($route);
    }

    /**
     * @return array<string, mixed>
     */
    public function unshareWithLearner(int $lessonId): array
    {
        $lesson = $this->requireInstructorLesson($lessonId);
        $route = $this->requireRouteForLesson((int) $lesson->id, (int) $lesson->organisation_id);

        $now = $this->nowUtc();
        $route->learner_visible = false;
        $route->shared_at = null;
        $route->updated_at = $now;
        $route->save(false, ['learner_visible', 'shared_at', 'updated_at']);

        return $this->serializeInstructor($route);
    }

    /**
     * Soft-delete — clears learner visibility and marks deleted.
     *
     * @return array{status: string}
     */
    public function discard(int $lessonId): array
    {
        $lesson = $this->requireInstructorLesson($lessonId);
        $route = LessonRoute::findOne([
            'lesson_id' => (int) $lesson->id,
            'organisation_id' => (int) $lesson->organisation_id,
        ]);
        if ($route === null || $route->deleted_at !== null) {
            return ['status' => 'ok'];
        }

        $now = $this->nowUtc();
        $route->status = LessonRoute::STATUS_DISCARDED;
        $route->learner_visible = false;
        $route->shared_at = null;
        $route->encoded_polyline = null;
        $route->bounds_json = null;
        $route->point_count = 0;
        $route->distance_metres = null;
        $route->deleted_at = $now;
        $route->updated_at = $now;
        $route->save(false);

        return ['status' => 'ok'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forInstructorLesson(int $lessonId): ?array
    {
        $lesson = $this->requireInstructorLesson($lessonId);
        $route = LessonRoute::findOne([
            'lesson_id' => (int) $lesson->id,
            'organisation_id' => (int) $lesson->organisation_id,
        ]);
        if ($route === null || $route->deleted_at !== null) {
            return null;
        }

        return $this->serializeInstructor($route);
    }

    /**
     * Learner-visible routes only.
     *
     * @return list<array<string, mixed>>
     */
    public function listForPortalLearner(): array
    {
        $account = PortalContext::requireAccount();
        /** @var LessonRoute[] $routes */
        $routes = LessonRoute::find()
            ->andWhere([
                'organisation_id' => (int) $account->organisation_id,
                'learner_id' => (int) $account->learner_id,
                'learner_visible' => true,
                'status' => LessonRoute::STATUS_COMPLETED,
                'deleted_at' => null,
            ])
            ->orderBy(['started_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(50)
            ->all();

        return array_map(fn (LessonRoute $r) => $this->serializeLearnerSummary($r), $routes);
    }

    /**
     * @return array<string, mixed>
     */
    public function portalDetail(int $routeId): array
    {
        $account = PortalContext::requireAccount();
        $route = LessonRoute::findOne([
            'id' => $routeId,
            'organisation_id' => (int) $account->organisation_id,
            'learner_id' => (int) $account->learner_id,
            'learner_visible' => true,
            'status' => LessonRoute::STATUS_COMPLETED,
            'deleted_at' => null,
        ]);
        if ($route === null) {
            throw new NotFoundHttpException('Route not found.');
        }

        return $this->serializeLearnerDetail($route);
    }

    /**
     * @param list<mixed> $rawPoints
     * @return list<array{0: float, 1: float}>
     */
    private function normalizePoints(array $rawPoints): array
    {
        $out = [];
        $prev = null;
        foreach ($rawPoints as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $lat = isset($raw['lat']) ? (float) $raw['lat'] : (isset($raw[0]) ? (float) $raw[0] : null);
            $lng = isset($raw['lng']) ? (float) $raw['lng'] : (isset($raw[1]) ? (float) $raw[1] : null);
            if ($lat === null || $lng === null) {
                continue;
            }
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }
            // Drop very inaccurate readings when accuracy is provided (metres).
            if (isset($raw['accuracy']) && is_numeric($raw['accuracy']) && (float) $raw['accuracy'] > 80) {
                continue;
            }
            // Drop near-duplicates (< ~8m) to keep geometry lean.
            if ($prev !== null) {
                $d = EncodedPolyline::approximateDistanceMetres([$prev, [$lat, $lng]]);
                if ($d < 8) {
                    continue;
                }
            }
            $point = [$lat, $lng];
            $out[] = $point;
            $prev = $point;
        }

        return $out;
    }

    private function requireInstructorLesson(int $lessonId): Lesson
    {
        $orgId = TenantContext::requireOrganisationId();
        $lesson = Lesson::findOne([
            'id' => $lessonId,
            'organisation_id' => $orgId,
        ]);
        if ($lesson === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        return $lesson;
    }

    private function requireRouteForLesson(int $lessonId, int $organisationId): LessonRoute
    {
        $route = LessonRoute::findOne([
            'lesson_id' => $lessonId,
            'organisation_id' => $organisationId,
        ]);
        if ($route === null || $route->deleted_at !== null) {
            throw new NotFoundHttpException('No route recording for this lesson.');
        }

        return $route;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInstructor(LessonRoute $route): array
    {
        return [
            'id' => (int) $route->id,
            'lesson_id' => (int) $route->lesson_id,
            'status' => $route->status,
            'started_at' => $route->started_at,
            'ended_at' => $route->ended_at,
            'duration_seconds' => $route->duration_seconds !== null ? (int) $route->duration_seconds : null,
            'duration_label' => $this->durationLabel($route->duration_seconds),
            'distance_metres' => $route->distance_metres !== null ? (int) $route->distance_metres : null,
            'distance_label' => $this->distanceLabel($route->distance_metres),
            'point_count' => (int) $route->point_count,
            'learner_visible' => (bool) $route->learner_visible,
            'shared_at' => $route->shared_at,
            'has_geometry' => $route->encoded_polyline !== null && $route->encoded_polyline !== '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLearnerSummary(LessonRoute $route): array
    {
        $lesson = $route->lesson;
        $skills = $lesson !== null
            ? $this->progress->skillsForLesson((int) $lesson->id, (int) $route->organisation_id)
            : [];

        return [
            'id' => (int) $route->id,
            'lesson_id' => (int) $route->lesson_id,
            'date_label' => $this->dateLabel($route->started_at ?? $route->created_at),
            'started_at' => $route->started_at,
            'duration_seconds' => $route->duration_seconds !== null ? (int) $route->duration_seconds : null,
            'duration_label' => $this->durationLabel($route->duration_seconds),
            'distance_metres' => $route->distance_metres !== null ? (int) $route->distance_metres : null,
            'distance_label' => $this->distanceLabel($route->distance_metres),
            'label' => $route->label,
            'skills' => array_map(static fn (array $s) => $s['label'], $skills),
            'bounds' => $this->decodeBounds($route),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLearnerDetail(LessonRoute $route): array
    {
        $summary = $this->serializeLearnerSummary($route);
        $lesson = $route->lesson;
        $summary['encoded_polyline'] = $route->encoded_polyline;
        $summary['learner_summary'] = $lesson?->learner_summary;
        $summary['next_focus'] = $lesson?->next_focus;
        $summary['skills_detail'] = $lesson !== null
            ? $this->progress->skillsForLesson((int) $lesson->id, (int) $route->organisation_id)
            : [];

        return $summary;
    }

    /**
     * @return array{north: float, south: float, east: float, west: float}|null
     */
    private function decodeBounds(LessonRoute $route): ?array
    {
        if ($route->bounds_json === null || $route->bounds_json === '') {
            return null;
        }
        try {
            $decoded = json_decode($route->bounds_json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return [
            'north' => (float) ($decoded['north'] ?? 0),
            'south' => (float) ($decoded['south'] ?? 0),
            'east' => (float) ($decoded['east'] ?? 0),
            'west' => (float) ($decoded['west'] ?? 0),
        ];
    }

    private function durationLabel(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) {
            return $h . 'h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'm';
        }

        return $m . 'm';
    }

    private function distanceLabel(?int $metres): ?string
    {
        if ($metres === null || $metres <= 0) {
            return null;
        }
        if ($metres < 1000) {
            return $metres . ' m';
        }
        $km = round($metres / 1000, 1);

        return $km . ' km';
    }

    private function dateLabel(string $utcDatetime): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utcDatetime, new DateTimeZone('UTC'));
        if ($dt === false) {
            return $utcDatetime;
        }

        return strtoupper($dt->format('j M'));
    }

    private function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
