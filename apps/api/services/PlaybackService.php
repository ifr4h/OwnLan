<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\PortalContext;
use app\models\Learner;
use app\models\Lesson;
use app\models\LessonResource;
use app\models\LessonRoute;
use app\models\Organisation;
use app\models\RouteMoment;
use app\models\LearningActivity;
use app\models\LearningContent;
use DateTimeImmutable;
use DateTimeZone;
use yii\web\NotFoundHttpException;

/**
 * Lesson Playback — reconstructs a lesson from route + moments + resources + skills.
 * RouteMoment is the LessonMoment implementation (no parallel event store).
 */
class PlaybackService
{
    private ProgressService $progress;

    public function __construct(?ProgressService $progress = null)
    {
        $this->progress = $progress ?? new ProgressService();
    }

    /**
     * @return array<string, mixed>
     */
    public function forPortalLesson(int $lessonId): array
    {
        $account = PortalContext::requireAccount();
        $lesson = Lesson::findOne([
            'id' => $lessonId,
            'organisation_id' => (int) $account->organisation_id,
            'learner_id' => (int) $account->learner_id,
            'status' => Lesson::STATUS_COMPLETED,
        ]);
        if ($lesson === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        /** @var Organisation $org */
        $org = Organisation::findOne((int) $account->organisation_id);
        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);

        $route = LessonRoute::findOne([
            'lesson_id' => $lessonId,
            'organisation_id' => (int) $account->organisation_id,
            'learner_id' => (int) $account->learner_id,
            'learner_visible' => true,
            'deleted_at' => null,
            'status' => LessonRoute::STATUS_COMPLETED,
        ]);

        /** @var RouteMoment[] $moments */
        $moments = [];
        if ($route !== null) {
            $moments = RouteMoment::find()
                ->andWhere([
                    'lesson_route_id' => (int) $route->id,
                    'learner_visible' => true,
                ])
                ->orderBy(['offset_seconds' => SORT_ASC, 'id' => SORT_ASC])
                ->all();
        }

        /** @var LessonResource[] $resources */
        $resources = LessonResource::find()
            ->andWhere([
                'lesson_id' => $lessonId,
                'organisation_id' => (int) $account->organisation_id,
                'learner_id' => (int) $account->learner_id,
                'learner_visible' => true,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $skills = $this->progress->skillsForLesson($lessonId, (int) $account->organisation_id);
        $chapters = $this->buildChapters($lesson, $moments, $resources);
        $timeline = $this->buildTimeline($moments, $resources, $route);

        $resourceByMoment = [];
        foreach ($resources as $res) {
            if ($res->route_moment_id !== null) {
                $resourceByMoment[(int) $res->route_moment_id] = $res;
            }
        }

        $momentPayloads = [];
        foreach ($moments as $m) {
            $res = $resourceByMoment[(int) $m->id] ?? null;
            $scene = null;
            if ($res !== null) {
                try {
                    $scene = json_decode($res->scene_json, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $scene = [];
                }
            }
            $momentPayloads[] = [
                'id' => (int) $m->id,
                'type' => 'INSTRUCTOR_MARKER',
                'kind' => $m->kind,
                'label' => $m->label ?: 'Marked moment',
                'learner_note' => $m->learner_note,
                'offset_seconds' => $m->offset_seconds !== null ? (int) $m->offset_seconds : null,
                'offset_label' => $this->offsetLabel($m->offset_seconds),
                'lat' => (float) $m->lat,
                'lng' => (float) $m->lng,
                'provenance' => 'instructor',
                'explanation' => $res !== null ? [
                    'resource_id' => (int) $res->id,
                    'title' => $res->title,
                    'note' => $res->learner_visible_note,
                    'scene' => is_array($scene) ? $scene : [],
                    'skill_codes' => $res->skillCodes(),
                ] : null,
                'related_learn_slugs' => $this->relatedSlugs($m, $res),
            ];
        }

        return [
            'lesson_id' => (int) $lesson->id,
            'date_label' => $local->format('j F Y'),
            'duration_minutes' => (int) $lesson->duration_minutes,
            'duration_label' => $this->durationLabel((int) $lesson->duration_minutes),
            'learner_summary' => $lesson->learner_summary,
            'next_focus' => $lesson->next_focus,
            'skills' => array_map(static fn (array $s) => [
                'code' => $s['code'],
                'label' => $s['label'],
                'category_label' => $s['category_label'],
            ], $skills),
            'route' => $route !== null ? [
                'id' => (int) $route->id,
                'encoded_polyline' => $route->encoded_polyline,
                'duration_seconds' => $route->duration_seconds !== null ? (int) $route->duration_seconds : null,
                'distance_metres' => $route->distance_metres !== null ? (int) $route->distance_metres : null,
                'bounds' => $this->bounds($route),
            ] : null,
            'moments' => $momentPayloads,
            'chapters' => $chapters,
            'timeline' => $timeline,
            'resources' => array_map(static function (LessonResource $r) {
                try {
                    $scene = json_decode($r->scene_json, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $scene = [];
                }

                return [
                    'id' => (int) $r->id,
                    'title' => $r->title,
                    'note' => $r->learner_visible_note,
                    'route_moment_id' => $r->route_moment_id !== null ? (int) $r->route_moment_id : null,
                    'scene' => is_array($scene) ? $scene : [],
                    'provenance' => 'instructor',
                ];
            }, $resources),
        ];
    }

    /**
     * Record learning activity — never touches instructor assessments.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordActivity(array $data): array
    {
        $account = PortalContext::requireAccount();
        $type = (string) ($data['activity_type'] ?? LearningActivity::TYPE_OPENED);
        if (!in_array($type, [
            LearningActivity::TYPE_OPENED,
            LearningActivity::TYPE_COMPLETED,
            LearningActivity::TYPE_SCENARIO,
            LearningActivity::TYPE_QUICK_REVIEW,
        ], true)) {
            $type = LearningActivity::TYPE_OPENED;
        }

        $slug = isset($data['content_slug']) ? (string) $data['content_slug'] : null;
        $contentId = null;
        if ($slug) {
            $content = LearningContent::findOne(['slug' => $slug, 'status' => LearningContent::STATUS_PUBLISHED]);
            $contentId = $content !== null ? (int) $content->id : null;
        }

        $row = new LearningActivity();
        $row->organisation_id = (int) $account->organisation_id;
        $row->learner_id = (int) $account->learner_id;
        $row->content_id = $contentId;
        $row->content_slug = $slug;
        $row->activity_type = $type;
        $result = $data['result'] ?? null;
        $row->result_json = is_array($result) ? json_encode($result, JSON_THROW_ON_ERROR) : null;
        $row->created_at = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $row->save(false);

        return [
            'id' => (int) $row->id,
            'activity_type' => $row->activity_type,
            'provenance' => 'learner',
            'note' => 'Interactive learning does not change instructor progress.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function journeyExpanded(): array
    {
        $account = PortalContext::requireAccount();
        $home = new PortalHomeService();
        $base = $home->journey();

        $practiceMinutes = (int) ( \app\models\PrivatePracticeSession::find()
            ->andWhere([
                'organisation_id' => (int) $account->organisation_id,
                'learner_id' => (int) $account->learner_id,
            ])
            ->sum('duration_minutes') ?? 0);

        $activities = (int) LearningActivity::find()
            ->andWhere([
                'organisation_id' => (int) $account->organisation_id,
                'learner_id' => (int) $account->learner_id,
            ])
            ->count();

        $base['private_practice_minutes'] = $practiceMinutes;
        $base['private_practice_hours'] = round($practiceMinutes / 60, 1);
        $base['learning_activities'] = $activities;
        $base['provenance'] = [
            'instructor_hours' => 'instructor_lessons',
            'private_practice_hours' => 'learner_self_report',
            'learning_activities' => 'learner',
        ];

        return $base;
    }

    /**
     * @param RouteMoment[] $moments
     * @param LessonResource[] $resources
     * @return list<array<string, mixed>>
     */
    private function buildChapters(Lesson $lesson, array $moments, array $resources): array
    {
        $chapters = [
            [
                'offset_seconds' => 0,
                'offset_label' => '0:00',
                'label' => 'Lesson started',
                'type' => 'START',
            ],
        ];
        foreach ($moments as $m) {
            $chapters[] = [
                'offset_seconds' => $m->offset_seconds !== null ? (int) $m->offset_seconds : null,
                'offset_label' => $this->offsetLabel($m->offset_seconds) ?? '—',
                'label' => $m->label ?: 'Review moment',
                'type' => 'INSTRUCTOR_MARKER',
                'moment_id' => (int) $m->id,
            ];
        }
        foreach ($resources as $r) {
            if ($r->route_moment_id !== null) {
                continue;
            }
            $chapters[] = [
                'offset_seconds' => null,
                'offset_label' => null,
                'label' => $r->title,
                'type' => 'TEACHING_EXPLANATION',
                'resource_id' => (int) $r->id,
            ];
        }
        $endOffset = $lesson->duration_minutes * 60;
        $chapters[] = [
            'offset_seconds' => $endOffset,
            'offset_label' => $this->offsetLabel($endOffset),
            'label' => 'Finish',
            'type' => 'END',
        ];

        return $chapters;
    }

    /**
     * @param RouteMoment[] $moments
     * @param LessonResource[] $resources
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(array $moments, array $resources, ?LessonRoute $route): array
    {
        $items = [];
        foreach ($moments as $m) {
            $items[] = [
                'kind' => 'moment',
                'id' => (int) $m->id,
                'offset_seconds' => $m->offset_seconds !== null ? (int) $m->offset_seconds : 0,
            ];
        }
        usort($items, static fn ($a, $b) => $a['offset_seconds'] <=> $b['offset_seconds']);

        return $items;
    }

    /**
     * @return list<string>
     */
    private function relatedSlugs(RouteMoment $m, ?LessonResource $res): array
    {
        $codes = $res?->skillCodes() ?? [];
        if ($m->kind === RouteMoment::KIND_ROUNDABOUT || str_contains(mb_strtolower((string) $m->label), 'roundabout')) {
            return ['interactive-third-exit', 'spiral-roundabouts'];
        }
        if (in_array('meeting', $codes, true)) {
            return ['interactive-meeting'];
        }

        return array_slice($codes !== [] ? ['interactive-third-exit'] : [], 0, 2);
    }

    /**
     * @return array{north: float, south: float, east: float, west: float}|null
     */
    private function bounds(LessonRoute $route): ?array
    {
        if (!$route->bounds_json) {
            return null;
        }
        try {
            $b = json_decode($route->bounds_json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($b) ? [
            'north' => (float) ($b['north'] ?? 0),
            'south' => (float) ($b['south'] ?? 0),
            'east' => (float) ($b['east'] ?? 0),
            'west' => (float) ($b['west'] ?? 0),
        ] : null;
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

    private function durationLabel(int $minutes): string
    {
        if ($minutes % 60 === 0) {
            $h = intdiv($minutes, 60);

            return $h === 1 ? '1 hr' : $h . ' hrs';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? $h . 'h ' . $m . 'm' : $m . ' min';
    }
}
