<?php

declare(strict_types=1);

namespace app\services;

use app\components\PortalContext;
use app\components\TenantContext;
use app\models\LearnerSkillProgress;
use app\models\Lesson;
use app\models\LessonSkill;
use app\models\ProgressSkill;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Lean skill catalogue + lesson tagging + historical ratings.
 * Ratings are instructional evidence — never pass-probability scores.
 */
class ProgressService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        /** @var ProgressSkill[] $skills */
        $skills = ProgressSkill::find()
            ->andWhere(['active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $byCategory = [];
        foreach ($skills as $skill) {
            $cat = $skill->category_code;
            if (!isset($byCategory[$cat])) {
                $byCategory[$cat] = [
                    'code' => $cat,
                    'label' => $skill->category_label,
                    'skills' => [],
                ];
            }
            $byCategory[$cat]['skills'][] = [
                'id' => (int) $skill->id,
                'code' => $skill->code,
                'label' => $skill->label,
            ];
        }

        return array_values($byCategory);
    }

    /**
     * Replace skill tags for a lesson and optionally append ratings.
     *
     * @param list<int|string> $skillIds
     * @param array<int|string, string> $ratings skill_id => rating
     */
    public function syncLessonSkills(Lesson $lesson, array $skillIds, array $ratings = []): void
    {
        $orgId = (int) $lesson->organisation_id;
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $ids = [];
        foreach ($skillIds as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);

        if ($ids !== []) {
            $validCount = (int) ProgressSkill::find()
                ->andWhere(['id' => $ids, 'active' => true])
                ->count();
            if ($validCount !== count($ids)) {
                throw new BadRequestHttpException('One or more skills are invalid.');
            }
        }

        LessonSkill::deleteAll([
            'lesson_id' => (int) $lesson->id,
            'organisation_id' => $orgId,
        ]);

        foreach ($ids as $skillId) {
            $row = new LessonSkill();
            $row->organisation_id = $orgId;
            $row->lesson_id = (int) $lesson->id;
            $row->skill_id = $skillId;
            $row->created_at = $now;
            if (!$row->save()) {
                throw new BadRequestHttpException('Could not save lesson skills.');
            }
        }

        foreach ($ratings as $skillIdRaw => $rating) {
            $skillId = (int) $skillIdRaw;
            if ($skillId <= 0 || !in_array($rating, LearnerSkillProgress::RATINGS, true)) {
                continue;
            }
            if ($ids !== [] && !in_array($skillId, $ids, true)) {
                // Allow rating a skill even if not in practised list — but must be valid.
                $exists = ProgressSkill::find()->andWhere(['id' => $skillId, 'active' => true])->exists();
                if (!$exists) {
                    continue;
                }
            }

            $progress = new LearnerSkillProgress();
            $progress->organisation_id = $orgId;
            $progress->learner_id = (int) $lesson->learner_id;
            $progress->skill_id = $skillId;
            $progress->lesson_id = (int) $lesson->id;
            $progress->rating = $rating;
            $progress->recorded_at = $lesson->completed_at ?? $now;
            $progress->created_at = $now;
            if (!$progress->save()) {
                throw new BadRequestHttpException('Could not save skill progress.');
            }
        }
    }

    /**
     * @return list<array{id: int, code: string, label: string, category_code: string, category_label: string}>
     */
    public function skillsForLesson(int $lessonId, int $organisationId): array
    {
        /** @var LessonSkill[] $rows */
        $rows = LessonSkill::find()
            ->andWhere([
                'lesson_id' => $lessonId,
                'organisation_id' => $organisationId,
            ])
            ->with('skill')
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $skill = $row->skill;
            if ($skill === null) {
                continue;
            }
            $out[] = [
                'id' => (int) $skill->id,
                'code' => $skill->code,
                'label' => $skill->label,
                'category_code' => $skill->category_code,
                'category_label' => $skill->category_label,
            ];
        }

        return $out;
    }

    /**
     * Latest rating per skill for a learner, plus history for charts.
     *
     * @return array<string, mixed>
     */
    public function learnerProgress(int $learnerId, int $organisationId): array
    {
        /** @var ProgressSkill[] $skills */
        $skills = ProgressSkill::find()
            ->andWhere(['active' => true])
            ->orderBy(['sort_order' => SORT_ASC])
            ->all();

        /** @var LearnerSkillProgress[] $history */
        $history = LearnerSkillProgress::find()
            ->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => $organisationId,
            ])
            ->orderBy(['recorded_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $latestBySkill = [];
        $seriesBySkill = [];
        foreach ($history as $row) {
            $sid = (int) $row->skill_id;
            $latestBySkill[$sid] = $row;
            $seriesBySkill[$sid][] = [
                'rating' => $row->rating,
                'rank' => LearnerSkillProgress::RATING_RANK[$row->rating] ?? 0,
                'recorded_at' => $row->recorded_at,
                'lesson_id' => $row->lesson_id !== null ? (int) $row->lesson_id : null,
            ];
        }

        $categories = [];
        $confident = 0;
        $developing = 0;
        $practising = 0;
        $introduced = 0;

        foreach ($skills as $skill) {
            $sid = (int) $skill->id;
            $cat = $skill->category_code;
            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'code' => $cat,
                    'label' => $skill->category_label,
                    'skills' => [],
                ];
            }

            $latest = $latestBySkill[$sid] ?? null;
            $rating = $latest?->rating;
            if ($rating === LearnerSkillProgress::RATING_CONFIDENT) {
                $confident++;
            } elseif ($rating === LearnerSkillProgress::RATING_DEVELOPING) {
                $developing++;
            } elseif ($rating === LearnerSkillProgress::RATING_PRACTISING) {
                $practising++;
            } elseif ($rating === LearnerSkillProgress::RATING_INTRODUCED) {
                $introduced++;
            }

            $categories[$cat]['skills'][] = [
                'id' => $sid,
                'code' => $skill->code,
                'label' => $skill->label,
                'rating' => $rating,
                'rating_label' => $this->ratingLabel($rating),
                'recorded_at' => $latest?->recorded_at,
                'history' => $seriesBySkill[$sid] ?? [],
            ];
        }

        $practisedCounts = $this->practisedCounts($learnerId, $organisationId);

        return [
            'summary' => [
                'confident' => $confident,
                'developing' => $developing,
                'practising' => $practising,
                'introduced' => $introduced,
                'skills_with_rating' => $confident + $developing + $practising + $introduced,
            ],
            'categories' => array_values($categories),
            'practised_counts' => $practisedCounts,
            'insights' => $this->buildProgressInsights($history, $skills, $practisedCounts),
        ];
    }

    /**
     * Portal progress overview — learner-safe skill summary with self-assessment.
     *
     * @return array<string, mixed>
     */
    public function portalOverview(): array
    {
        $account = PortalContext::requireAccount();
        $learnerId = (int) $account->learner_id;
        $orgId = (int) $account->organisation_id;

        $payload = $this->learnerProgress($learnerId, $orgId);
        $selfService = new LearnerSelfAssessmentService();
        $latestSelf = $selfService->latestBySkill($learnerId, $orgId);

        foreach ($payload['categories'] as &$cat) {
            foreach ($cat['skills'] as &$skill) {
                $self = $latestSelf[(int) $skill['id']] ?? null;
                $skill['learner_confidence'] = $self['confidence'] ?? null;
                $skill['learner_confidence_label'] = $self['confidence_label'] ?? null;
            }
            unset($skill);
        }
        unset($cat);

        return $payload;
    }

    /**
     * Instructor progress view with mock evidence counts per skill.
     *
     * @return array<string, mixed>
     */
    public function instructorProgress(int $learnerId): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $payload = $this->learnerProgress($learnerId, $orgId);
        $mockService = new MockTestService();
        $selfService = new LearnerSelfAssessmentService();
        $latestSelf = $selfService->latestBySkill($learnerId, $orgId);

        foreach ($payload['categories'] as &$cat) {
            foreach ($cat['skills'] as &$skill) {
                $mockEvidence = $mockService->mockEvidenceForSkill(
                    $learnerId,
                    $orgId,
                    (int) $skill['id'],
                    3,
                );
                $skill['mock_evidence_count'] = count($mockEvidence);
                $skill['recent_mock_evidence'] = $mockEvidence;
                $self = $latestSelf[(int) $skill['id']] ?? null;
                $skill['learner_confidence'] = $self['confidence'] ?? null;
                $skill['learner_confidence_label'] = $self['confidence_label'] ?? null;
            }
            unset($skill);
        }
        unset($cat);

        $payload['mock_history'] = (new MockTestService())->listForLearner($learnerId);

        return $payload;
    }

    /**
     * @return list<array{skill_id: int, code: string, label: string, category_label: string, lesson_count: int}>
     */
    private function practisedCounts(int $learnerId, int $organisationId): array
    {
        $sql = <<<'SQL'
SELECT s.id AS skill_id, s.code, s.label, s.category_label, COUNT(DISTINCT ls.lesson_id) AS lesson_count
FROM lesson_skills ls
INNER JOIN lessons l ON l.id = ls.lesson_id
INNER JOIN progress_skills s ON s.id = ls.skill_id
WHERE ls.organisation_id = :org
  AND l.learner_id = :learner
  AND l.status = :completed
GROUP BY s.id, s.code, s.label, s.category_label, s.sort_order
ORDER BY lesson_count DESC, s.sort_order ASC
SQL;

        $rows = Yii::$app->db->createCommand($sql, [
            ':org' => $organisationId,
            ':learner' => $learnerId,
            ':completed' => Lesson::STATUS_COMPLETED,
        ])->queryAll();

        return array_map(static fn (array $r) => [
            'skill_id' => (int) $r['skill_id'],
            'code' => (string) $r['code'],
            'label' => (string) $r['label'],
            'category_label' => (string) $r['category_label'],
            'lesson_count' => (int) $r['lesson_count'],
        ], $rows);
    }

    /**
     * Deterministic insights from recorded ratings / practised tags only.
     *
     * @param LearnerSkillProgress[] $history
     * @param ProgressSkill[] $skills
     * @param list<array{skill_id: int, code: string, label: string, category_label: string, lesson_count: int}> $practised
     * @return list<string>
     */
    private function buildProgressInsights(array $history, array $skills, array $practised): array
    {
        $insights = [];
        $byCode = [];
        foreach ($skills as $skill) {
            $byCode[(int) $skill->id] = $skill;
        }

        // Improvement: last 3 ratings for a skill show rising rank.
        $bySkill = [];
        foreach ($history as $row) {
            $bySkill[(int) $row->skill_id][] = $row;
        }
        foreach ($bySkill as $skillId => $rows) {
            if (count($rows) < 3) {
                continue;
            }
            $last3 = array_slice($rows, -3);
            $r0 = LearnerSkillProgress::RATING_RANK[$last3[0]->rating] ?? 0;
            $r1 = LearnerSkillProgress::RATING_RANK[$last3[1]->rating] ?? 0;
            $r2 = LearnerSkillProgress::RATING_RANK[$last3[2]->rating] ?? 0;
            if ($r0 < $r1 && $r1 <= $r2) {
                $label = $byCode[$skillId]->label ?? 'This skill';
                $insights[] = $label . ' have improved across your last 3 recorded lessons.';
            }
        }

        foreach (array_slice($practised, 0, 3) as $item) {
            if ($item['lesson_count'] >= 3) {
                $insights[] = 'You\'ve practised '
                    . lcfirst($item['label'])
                    . ' in '
                    . $item['lesson_count']
                    . ' lessons.';
            }
        }

        // Recently introduced independent skills.
        $recentIndependent = false;
        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-21 days');
        foreach ($history as $row) {
            $skill = $byCode[(int) $row->skill_id] ?? null;
            if ($skill === null || $skill->category_code !== 'independent') {
                continue;
            }
            $at = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row->recorded_at, new DateTimeZone('UTC'));
            if ($at !== false && $at >= $cutoff) {
                $recentIndependent = true;
                break;
            }
        }
        if ($recentIndependent) {
            $insights[] = 'You\'ve recently started practising independent driving.';
        }

        return array_values(array_unique(array_slice($insights, 0, 6)));
    }

    private function ratingLabel(?string $rating): ?string
    {
        return match ($rating) {
            LearnerSkillProgress::RATING_INTRODUCED => 'Introduced',
            LearnerSkillProgress::RATING_PRACTISING => 'Practising',
            LearnerSkillProgress::RATING_DEVELOPING => 'Developing',
            LearnerSkillProgress::RATING_CONFIDENT => 'Feeling confident',
            default => null,
        };
    }

    public function requireTenantOrg(int $organisationId): void
    {
        $tenant = TenantContext::requireOrganisationId();
        if ($tenant !== $organisationId) {
            throw new ForbiddenHttpException('Wrong organisation.');
        }
    }

    public function requirePortalLearner(int $learnerId): void
    {
        $account = PortalContext::requireAccount();
        if ((int) $account->learner_id !== $learnerId) {
            throw new ForbiddenHttpException('Not your progress.');
        }
    }

    public function findSkillOrFail(int $id): ProgressSkill
    {
        $skill = ProgressSkill::findOne(['id' => $id, 'active' => true]);
        if ($skill === null) {
            throw new NotFoundHttpException('Skill not found.');
        }

        return $skill;
    }
}
