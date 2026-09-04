<?php

declare(strict_types=1);

namespace app\services;

use app\components\PortalContext;
use app\models\Learner;
use app\models\LearningContent;
use app\models\Lesson;
use app\models\LessonResource;
use app\models\LessonRoute;
use app\models\PrivatePracticeSession;
use app\models\ProgressSkill;
use app\models\RouteMoment;
use DateTimeImmutable;
use DateTimeZone;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Learner Learn personalisation + library + skill evidence + private practice.
 * Deterministic only — no paid AI.
 */
class LearnService
{
    private ProgressService $progress;

    public function __construct(?ProgressService $progress = null)
    {
        $this->progress = $progress ?? new ProgressService();
    }

    /**
     * @return array<string, mixed>
     */
    public function portalHome(): array
    {
        $account = PortalContext::requireAccount();
        $learnerId = (int) $account->learner_id;
        $orgId = (int) $account->organisation_id;

        /** @var Learner|null $learner */
        $learner = Learner::findOne(['id' => $learnerId, 'organisation_id' => $orgId]);
        if ($learner === null) {
            throw new NotFoundHttpException('Learner not found.');
        }

        $focus = $learner->next_focus;
        $forYou = [];
        $seenSlugs = [];

        $pushForYou = function (string $reason, string $label, LearningContent $content) use (&$forYou, &$seenSlugs): void {
            if (isset($seenSlugs[$content->slug])) {
                return;
            }
            $seenSlugs[$content->slug] = true;
            $forYou[] = [
                'reason' => $reason,
                'reason_label' => $label,
                'content' => $this->serializeContentSummary($content),
            ];
        };

        // Instructor intent first: next focus → interactive content preferred.
        if ($focus) {
            $matched = $this->matchContentsByText($focus, 4);
            usort($matched, static function (LearningContent $a, LearningContent $b): int {
                $ai = str_starts_with($a->slug, 'interactive-') ? 0 : 1;
                $bi = str_starts_with($b->slug, 'interactive-') ? 0 : 1;

                return $ai <=> $bi;
            });
            foreach (array_slice($matched, 0, 2) as $content) {
                $pushForYou('next_focus', 'Your next focus', $content);
            }
        }

        /** @var LessonResource[] $fromLesson */
        $fromLesson = LessonResource::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerId,
                'learner_visible' => true,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->limit(5)
            ->all();

        foreach ($fromLesson as $res) {
            foreach ($this->matchContentsBySkills($res->skillCodes(), 1) as $content) {
                $pushForYou('from_instructor', 'From your instructor', $content);
            }
        }

        $lessonItems = [];
        foreach ($fromLesson as $res) {
            $lessonItems[] = [
                'id' => (int) $res->id,
                'title' => $res->title,
                'note' => $res->learner_visible_note,
                'lesson_id' => (int) $res->lesson_id,
                'skill_codes' => $res->skillCodes(),
            ];
        }

        /** @var RouteMoment[] $moments */
        $moments = RouteMoment::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerId,
                'learner_visible' => true,
            ])
            ->andWhere(['not', ['learner_note' => null]])
            ->orderBy(['id' => SORT_DESC])
            ->limit(5)
            ->all();

        $momentItems = array_map(static fn (RouteMoment $m) => [
            'id' => (int) $m->id,
            'label' => $m->label ?: 'Place to review',
            'note' => $m->learner_note,
            'lesson_id' => (int) $m->lesson_id,
            'route_id' => (int) $m->lesson_route_id,
        ], $moments);

        foreach ($moments as $m) {
            $text = trim(($m->label ?? '') . ' ' . ($m->learner_note ?? ''));
            if ($text === '') {
                continue;
            }
            foreach ($this->matchContentsByText($text, 1) as $content) {
                $pushForYou('from_last_lesson', 'From your last lesson', $content);
            }
        }

        $progress = $this->progress->learnerProgress($learnerId, $orgId);
        $recentSkills = array_slice($progress['practised_counts'] ?? [], 0, 4);

        // Quick review — last opened interactive content older than 2 days, or first interactive for focus.
        $quickReview = null;
        $interactive = LearningContent::find()
            ->andWhere(['status' => LearningContent::STATUS_PUBLISHED])
            ->andWhere(['like', 'slug', 'interactive-', false])
            ->limit(12)
            ->all();
        if ($focus && $interactive !== []) {
            $focusMatched = $this->matchContentsByText($focus, 6);
            foreach ($focusMatched as $c) {
                if (str_starts_with($c->slug, 'interactive-')) {
                    $quickReview = [
                        'reason' => 'quick_review',
                        'reason_label' => 'Quick review',
                        'content' => $this->serializeContentSummary($c),
                        'prompt' => 'A short reminder — what would you do here?',
                    ];
                    break;
                }
            }
        }
        if ($quickReview === null && $interactive !== []) {
            $quickReview = [
                'reason' => 'quick_review',
                'reason_label' => 'Quick review',
                'content' => $this->serializeContentSummary($interactive[0]),
                'prompt' => 'A short reminder — what would you do here?',
            ];
        }

        $explore = LearningContent::find()
            ->andWhere(['status' => LearningContent::STATUS_PUBLISHED])
            ->andWhere([
                'or',
                ['organisation_id' => null],
                ['organisation_id' => $orgId],
            ])
            ->orderBy(['category' => SORT_ASC, 'title' => SORT_ASC])
            ->all();

        $byCategory = [];
        foreach ($explore as $content) {
            $cat = $content->category;
            if (!isset($byCategory[$cat])) {
                $byCategory[$cat] = [
                    'code' => $cat,
                    'label' => $this->categoryLabel($cat),
                    'items' => [],
                ];
            }
            $byCategory[$cat]['items'][] = $this->serializeContentSummary($content);
        }

        return [
            'next_focus' => $focus,
            'for_you' => array_slice($forYou, 0, 6),
            'quick_review' => $quickReview,
            'from_last_lessons' => $lessonItems,
            'places_to_review' => $momentItems,
            'recently_practised' => $recentSkills,
            'explore' => array_values($byCategory),
            'saved_count' => count($lessonItems),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function content(string $slug): array
    {
        $account = PortalContext::requireAccount();
        $orgId = (int) $account->organisation_id;
        $content = LearningContent::find()
            ->andWhere(['slug' => $slug, 'status' => LearningContent::STATUS_PUBLISHED])
            ->andWhere([
                'or',
                ['organisation_id' => null],
                ['organisation_id' => $orgId],
            ])
            ->one();
        if (!$content instanceof LearningContent) {
            throw new NotFoundHttpException('Resource not found.');
        }

        return [
            'id' => (int) $content->id,
            'slug' => $content->slug,
            'title' => $content->title,
            'category' => $content->category,
            'category_label' => $this->categoryLabel($content->category),
            'summary' => $content->summary,
            'blocks' => $content->blocks(),
            'skill_codes' => $content->skillCodes(),
            'source_note' => $content->source_note,
        ];
    }

    /**
     * Skill evidence — assessments, lessons, routes, resources. No fabricated milestones.
     *
     * @return array<string, mixed>
     */
    public function skillEvidence(string $skillCode): array
    {
        $account = PortalContext::requireAccount();
        $learnerId = (int) $account->learner_id;
        $orgId = (int) $account->organisation_id;

        $skill = ProgressSkill::findOne(['code' => $skillCode, 'active' => true]);
        if ($skill === null) {
            throw new NotFoundHttpException('Skill not found.');
        }

        $progress = $this->progress->learnerProgress($learnerId, $orgId);
        $skillPayload = null;
        foreach ($progress['categories'] as $cat) {
            foreach ($cat['skills'] as $s) {
                if ($s['code'] === $skillCode) {
                    $skillPayload = $s;
                    break 2;
                }
            }
        }

        $timeline = [];
        foreach ($skillPayload['history'] ?? [] as $row) {
            $timeline[] = [
                'type' => 'assessment',
                'rating' => $row['rating'],
                'recorded_at' => $row['recorded_at'],
                'lesson_id' => $row['lesson_id'],
            ];
        }

        /** @var Lesson[] $lessons */
        $sqlLessons = Lesson::find()
            ->alias('l')
            ->innerJoin('lesson_skills ls', 'ls.lesson_id = l.id')
            ->innerJoin('progress_skills ps', 'ps.id = ls.skill_id')
            ->andWhere([
                'l.organisation_id' => $orgId,
                'l.learner_id' => $learnerId,
                'l.status' => Lesson::STATUS_COMPLETED,
                'ps.code' => $skillCode,
            ])
            ->orderBy(['l.starts_at' => SORT_DESC])
            ->limit(20)
            ->all();

        $lessonItems = [];
        foreach ($sqlLessons as $lesson) {
            $lessonItems[] = [
                'id' => (int) $lesson->id,
                'starts_at' => $lesson->starts_at,
                'learner_summary' => $lesson->learner_summary,
                'next_focus' => $lesson->next_focus,
            ];
            $timeline[] = [
                'type' => 'practised',
                'recorded_at' => $lesson->completed_at ?? $lesson->starts_at,
                'lesson_id' => (int) $lesson->id,
            ];
        }

        /** @var LessonRoute[] $routes */
        $routes = LessonRoute::find()
            ->alias('r')
            ->innerJoin('lesson_skills ls', 'ls.lesson_id = r.lesson_id')
            ->innerJoin('progress_skills ps', 'ps.id = ls.skill_id')
            ->andWhere([
                'r.organisation_id' => $orgId,
                'r.learner_id' => $learnerId,
                'r.learner_visible' => true,
                'r.deleted_at' => null,
                'ps.code' => $skillCode,
            ])
            ->orderBy(['r.started_at' => SORT_DESC])
            ->limit(10)
            ->all();

        $routeItems = array_map(static fn (LessonRoute $r) => [
            'id' => (int) $r->id,
            'lesson_id' => (int) $r->lesson_id,
            'started_at' => $r->started_at,
            'duration_seconds' => $r->duration_seconds,
        ], $routes);

        foreach ($routes as $r) {
            $timeline[] = [
                'type' => 'route',
                'recorded_at' => $r->started_at,
                'route_id' => (int) $r->id,
                'lesson_id' => (int) $r->lesson_id,
            ];
        }

        usort($timeline, static fn ($a, $b) => strcmp((string) $a['recorded_at'], (string) $b['recorded_at']));

        $related = $this->matchContentsBySkills([$skillCode], 5);

        /** @var Learner $learner */
        $learner = Learner::findOne(['id' => $learnerId]);

        return [
            'skill' => [
                'code' => $skill->code,
                'label' => $skill->label,
                'category_label' => $skill->category_label,
                'rating' => $skillPayload['rating'] ?? null,
                'rating_label' => $skillPayload['rating_label'] ?? null,
            ],
            'next_focus' => $learner?->next_focus,
            'timeline' => $timeline,
            'lessons' => $lessonItems,
            'routes' => $routeItems,
            'related_resources' => array_map(fn (LearningContent $c) => $this->serializeContentSummary($c), $related),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function logPrivatePractice(array $data): array
    {
        $account = PortalContext::requireAccount();
        $now = $this->now();
        $minutes = (int) ($data['duration_minutes'] ?? 0);
        if ($minutes < 5 || $minutes > 480) {
            throw new BadRequestHttpException('Duration must be between 5 and 480 minutes.');
        }

        $session = new PrivatePracticeSession();
        $session->organisation_id = (int) $account->organisation_id;
        $session->learner_id = (int) $account->learner_id;
        $session->practised_at = $now;
        if (!empty($data['practised_at']) && is_string($data['practised_at'])) {
            $session->practised_at = $data['practised_at'];
        }
        $session->duration_minutes = $minutes;
        $skills = is_array($data['skill_codes'] ?? null) ? array_values($data['skill_codes']) : [];
        $session->skill_codes_json = $skills !== [] ? json_encode($skills, JSON_THROW_ON_ERROR) : null;
        $feeling = $data['feeling'] ?? null;
        $session->feeling = in_array($feeling, [
            PrivatePracticeSession::FEELING_DIFFICULT,
            PrivatePracticeSession::FEELING_OKAY,
            PrivatePracticeSession::FEELING_COMFORTABLE,
        ], true) ? $feeling : null;
        $note = trim((string) ($data['note'] ?? ''));
        $session->note = $note === '' ? null : $note;
        $session->created_at = $now;
        $session->updated_at = $now;
        if (!$session->save()) {
            throw new BadRequestHttpException('Could not save private practice.');
        }

        // Explicit: do NOT write LearnerSkillProgress here.
        return $this->serializePractice($session);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function portalPracticeList(): array
    {
        $account = PortalContext::requireAccount();
        /** @var PrivatePracticeSession[] $rows */
        $rows = PrivatePracticeSession::find()
            ->andWhere([
                'organisation_id' => (int) $account->organisation_id,
                'learner_id' => (int) $account->learner_id,
            ])
            ->orderBy(['practised_at' => SORT_DESC])
            ->limit(40)
            ->all();

        return array_map(fn (PrivatePracticeSession $s) => $this->serializePractice($s), $rows);
    }

    /**
     * Concise instructor view since last completed lesson.
     *
     * @return array<string, mixed>|null
     */
    public function instructorSinceLastLesson(int $learnerId): ?array
    {
        $orgId = \app\components\TenantContext::requireOrganisationId();
        $last = Lesson::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerId,
                'status' => Lesson::STATUS_COMPLETED,
            ])
            ->orderBy(['starts_at' => SORT_DESC])
            ->one();

        $since = $last?->completed_at ?? $last?->starts_at;
        $q = PrivatePracticeSession::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerId,
            ]);
        if ($since) {
            $q->andWhere(['>', 'practised_at', $since]);
        }
        /** @var PrivatePracticeSession[] $rows */
        $rows = $q->orderBy(['practised_at' => SORT_DESC])->limit(20)->all();
        if ($rows === []) {
            return null;
        }

        $minutes = 0;
        $skills = [];
        foreach ($rows as $row) {
            $minutes += (int) $row->duration_minutes;
            foreach ($row->skillCodes() as $code) {
                $skills[$code] = true;
            }
        }

        return [
            'drives' => count($rows),
            'total_minutes' => $minutes,
            'duration_label' => $this->practiceDurationLabel($minutes),
            'skill_codes' => array_keys($skills),
            'companion_notes' => $companionNotes,
            'sessions' => array_map(fn (PrivatePracticeSession $s) => $this->serializePractice($s), $rows),
            'provenance' => [
                'practice' => 'learner_self_report',
                'companion_notes' => 'companion',
                'note' => 'Does not change instructor assessment.',
            ],
        ];
    }

    private function practiceDurationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . 'm';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m > 0 ? $h . 'h ' . $m . 'm' : $h . 'h';
    }

    /**
     * Admin/platform content list for Resource Builder.
     *
     * @return list<array<string, mixed>>
     */
    public function adminList(?string $status = null): array
    {
        $q = LearningContent::find()->orderBy(['updated_at' => SORT_DESC]);
        if ($status) {
            $q->andWhere(['status' => $status]);
        }

        return array_map(function (LearningContent $c) {
            return [
                'id' => (int) $c->id,
                'slug' => $c->slug,
                'title' => $c->title,
                'category' => $c->category,
                'status' => $c->status,
                'version' => (int) $c->version,
                'published_at' => $c->published_at,
                'updated_at' => $c->updated_at,
            ];
        }, $q->limit(200)->all());
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function adminSave(?int $id, array $data): array
    {
        $now = $this->now();
        $content = $id !== null
            ? LearningContent::findOne(['id' => $id])
            : new LearningContent();
        if ($content === null) {
            throw new NotFoundHttpException('Content not found.');
        }

        $content->slug = trim((string) ($data['slug'] ?? $content->slug ?? ''));
        $content->title = trim((string) ($data['title'] ?? ''));
        $content->category = trim((string) ($data['category'] ?? 'general'));
        $content->summary = $this->nullable($data['summary'] ?? null);
        $blocks = $data['blocks'] ?? [];
        if (!is_array($blocks)) {
            throw new BadRequestHttpException('Blocks must be an array.');
        }
        $content->blocks_json = json_encode($blocks, JSON_THROW_ON_ERROR);
        $skills = is_array($data['skill_codes'] ?? null) ? $data['skill_codes'] : [];
        $content->skill_codes_json = json_encode(array_values($skills), JSON_THROW_ON_ERROR);
        $content->transmission = $this->nullable($data['transmission'] ?? null);
        $status = (string) ($data['status'] ?? LearningContent::STATUS_DRAFT);
        $content->status = in_array($status, [
            LearningContent::STATUS_DRAFT,
            LearningContent::STATUS_PUBLISHED,
            LearningContent::STATUS_ARCHIVED,
        ], true) ? $status : LearningContent::STATUS_DRAFT;
        if ($content->status === LearningContent::STATUS_PUBLISHED && $content->published_at === null) {
            $content->published_at = $now;
        }
        $content->source_note = $this->nullable($data['source_note'] ?? 'OwnLane original');
        $content->version = max(1, (int) ($content->version ?: 1));
        if ($content->isNewRecord) {
            $content->created_at = $now;
            $content->organisation_id = null;
        } else {
            $content->version = (int) $content->version + 1;
        }
        $content->updated_at = $now;
        if ($content->slug === '' || $content->title === '') {
            throw new BadRequestHttpException('Slug and title are required.');
        }
        if (!$content->save()) {
            throw new BadRequestHttpException('Could not save content.');
        }

        return $this->content($content->slug);
    }

    /**
     * @return list<LearningContent>
     */
    private function matchContentsByText(string $text, int $limit): array
    {
        $lower = mb_strtolower($text);
        $map = [
            'roundabout' => 'roundabouts',
            'spiral' => 'roundabouts',
            'junction' => 'junctions',
            'meeting' => 'junctions',
            'dual' => 'dual_carriageways',
            'carriageway' => 'dual_carriageways',
            'independent' => 'independent',
            'lane' => 'independent',
            'manoeuvre' => 'manoeuvres',
            'park' => 'manoeuvres',
        ];
        $categories = [];
        foreach ($map as $needle => $cat) {
            if (str_contains($lower, $needle)) {
                $categories[$cat] = true;
            }
        }
        if ($categories === []) {
            return [];
        }

        return LearningContent::find()
            ->andWhere(['status' => LearningContent::STATUS_PUBLISHED, 'category' => array_keys($categories)])
            ->limit($limit)
            ->all();
    }

    /**
     * @param list<string> $skillCodes
     * @return list<LearningContent>
     */
    private function matchContentsBySkills(array $skillCodes, int $limit): array
    {
        /** @var LearningContent[] $all */
        $all = LearningContent::find()
            ->andWhere(['status' => LearningContent::STATUS_PUBLISHED])
            ->all();
        $matched = [];
        foreach ($all as $content) {
            foreach ($content->skillCodes() as $code) {
                if (in_array($code, $skillCodes, true)) {
                    $matched[] = $content;
                    break;
                }
            }
            if (count($matched) >= $limit) {
                break;
            }
        }

        return $matched;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContentSummary(LearningContent $c): array
    {
        return [
            'slug' => $c->slug,
            'title' => $c->title,
            'category' => $c->category,
            'category_label' => $this->categoryLabel($c->category),
            'summary' => $c->summary,
            'skill_codes' => $c->skillCodes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePractice(PrivatePracticeSession $s): array
    {
        return [
            'id' => (int) $s->id,
            'practised_at' => $s->practised_at,
            'duration_minutes' => (int) $s->duration_minutes,
            'skill_codes' => $s->skillCodes(),
            'feeling' => $s->feeling,
            'note' => $s->note,
            'learner_reflection' => $s->note,
            'companion_note' => $s->companion_note,
            'provenance' => [
                'feeling' => 'learner_self_report',
                'note' => 'learner_self_report',
                'companion_note' => $s->companion_note !== null ? 'companion' : null,
            ],
        ];
    }

    private function categoryLabel(string $code): string
    {
        return match ($code) {
            'roundabouts' => 'Roundabouts',
            'junctions' => 'Junctions',
            'manoeuvres' => 'Manoeuvres',
            'dual_carriageways' => 'Dual carriageways',
            'independent' => 'Independent driving',
            'traffic_lights' => 'Traffic lights',
            'crossings' => 'Crossings',
            'signs' => 'Road signs',
            default => ucfirst(str_replace('_', ' ', $code)),
        };
    }

    private function nullable(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim((string) $v);

        return $t === '' ? null : $t;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
