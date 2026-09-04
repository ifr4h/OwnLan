<?php

declare(strict_types=1);

namespace app\services;

use app\components\PortalContext;
use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerSkillProgress;
use app\models\LearnerSkillSelfAssessment;
use app\models\Lesson;
use app\models\ProgressSkill;
use app\models\PrivatePracticeSession;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Learner self-assessment — separate provenance from instructor ratings.
 */
class LearnerSelfAssessmentService
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordForLearnerPortal(string $skillCode, array $data): array
    {
        $account = PortalContext::requireAccount();
        $learnerId = (int) $account->learner_id;
        $orgId = (int) $account->organisation_id;

        $skill = ProgressSkill::findOne(['code' => $skillCode, 'active' => true]);
        if ($skill === null) {
            throw new NotFoundHttpException('Skill not found.');
        }

        $confidence = (string) ($data['confidence'] ?? '');
        if (!in_array($confidence, LearnerSkillSelfAssessment::CONFIDENCES, true)) {
            throw new BadRequestHttpException('Choose how this feels for you.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $row = new LearnerSkillSelfAssessment();
        $row->organisation_id = $orgId;
        $row->learner_id = $learnerId;
        $row->skill_id = (int) $skill->id;
        $row->confidence = $confidence;
        $row->note = trim((string) ($data['note'] ?? '')) ?: null;
        $row->recorded_at = $now;
        $row->created_at = $now;
        if (!$row->save()) {
            throw new BadRequestHttpException('Could not save.');
        }

        return $this->serialize($row, $skill);
    }

    /**
     * Instructor view — full skill detail with all evidence sources.
     *
     * @return array<string, mixed>
     */
    public function instructorSkillDetail(int $learnerId, string $skillCode, MockTestService $mocks): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $this->assertLearnerInOrg($learnerId, $orgId);

        $skill = ProgressSkill::findOne(['code' => $skillCode, 'active' => true]);
        if ($skill === null) {
            throw new NotFoundHttpException('Skill not found.');
        }

        $progress = new ProgressService();
        $overview = $progress->learnerProgress($learnerId, $orgId);
        $skillPayload = null;
        foreach ($overview['categories'] as $cat) {
            foreach ($cat['skills'] as $s) {
                if ($s['code'] === $skillCode) {
                    $skillPayload = $s;
                    break 2;
                }
            }
        }

        $lessonEvidence = $this->lessonEvidence($learnerId, $orgId, (int) $skill->id);
        $mockEvidence = $mocks->mockEvidenceForSkill($learnerId, $orgId, (int) $skill->id);
        $selfHistory = $this->historyForSkill($learnerId, $orgId, (int) $skill->id);
        $practiceEvidence = $this->practiceEvidence($learnerId, $orgId, $skillCode);

        $latestSelf = $selfHistory[0] ?? null;
        $instructorRating = $skillPayload['rating'] ?? null;

        $disagreement = null;
        if ($latestSelf !== null && $instructorRating === LearnerSkillProgress::RATING_CONFIDENT
            && in_array($latestSelf['confidence'], [
                LearnerSkillSelfAssessment::CONFIDENCE_NEED_HELP,
                LearnerSkillSelfAssessment::CONFIDENCE_STILL_PRACTISING,
            ], true)) {
            $learner = Learner::findOne($learnerId);
            $name = $learner?->first_name ?? 'They';
            $disagreement = $name . ' would like more practice with this.';
        }

        /** @var Learner|null $learner */
        $learner = Learner::findOne($learnerId);

        return [
            'skill' => [
                'code' => $skill->code,
                'label' => $skill->label,
                'category_label' => $skill->category_label,
            ],
            'instructor_assessment' => [
                'rating' => $instructorRating,
                'rating_label' => $skillPayload['rating_label'] ?? null,
                'recorded_at' => $skillPayload['recorded_at'] ?? null,
                'history' => $skillPayload['history'] ?? [],
            ],
            'learner_self_assessment' => [
                'latest' => $latestSelf,
                'history' => $selfHistory,
            ],
            'evidence' => [
                'lessons' => $lessonEvidence,
                'mock_tests' => $mockEvidence,
                'private_practice' => $practiceEvidence,
            ],
            'next_focus' => $learner?->next_focus,
            'teaching_context' => $disagreement,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForSkill(int $learnerId, int $orgId, int $skillId, int $limit = 20): array
    {
        /** @var LearnerSkillSelfAssessment[] $rows */
        $rows = LearnerSkillSelfAssessment::find()
            ->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => $orgId,
                'skill_id' => $skillId,
            ])
            ->orderBy(['recorded_at' => SORT_DESC])
            ->limit($limit)
            ->all();

        return array_map(fn ($r) => $this->serialize($r, $r->skill), $rows);
    }

    public function latestBySkill(int $learnerId, int $orgId): array
    {
        $sql = <<<'SQL'
SELECT DISTINCT ON (skill_id) *
FROM learner_skill_self_assessments
WHERE learner_id = :learner AND organisation_id = :org
ORDER BY skill_id, recorded_at DESC
SQL;

        $rows = Yii::$app->db->createCommand($sql, [
            ':learner' => $learnerId,
            ':org' => $orgId,
        ])->queryAll();

        $out = [];
        foreach ($rows as $row) {
            $skill = ProgressSkill::findOne((int) $row['skill_id']);
            $out[(int) $row['skill_id']] = [
                'skill_id' => (int) $row['skill_id'],
                'skill_code' => $skill?->code,
                'confidence' => $row['confidence'],
                'confidence_label' => LearnerSkillSelfAssessment::labelFor($row['confidence']),
                'recorded_at' => $row['recorded_at'],
            ];
        }

        return $out;
    }

    /**
     * Recent self-assessments for Today/lesson context.
     *
     * @return list<array{skill_label: string, line: string}>
     */
    public function recentContextLines(int $learnerId, int $orgId, int $days = 14): array
    {
        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . $days . ' days')
            ->format('Y-m-d H:i:s');

        /** @var LearnerSkillSelfAssessment[] $rows */
        $rows = LearnerSkillSelfAssessment::find()
            ->andWhere(['learner_id' => $learnerId, 'organisation_id' => $orgId])
            ->andWhere(['>=', 'recorded_at', $cutoff])
            ->andWhere(['in', 'confidence', [
                LearnerSkillSelfAssessment::CONFIDENCE_NEED_HELP,
                LearnerSkillSelfAssessment::CONFIDENCE_STILL_PRACTISING,
            ]])
            ->with('skill')
            ->orderBy(['recorded_at' => SORT_DESC])
            ->limit(3)
            ->all();

        $learner = Learner::findOne($learnerId);
        $name = $learner?->first_name ?? 'They';
        $lines = [];
        foreach ($rows as $row) {
            $label = $row->skill?->label ?? 'this';
            $lines[] = [
                'skill_label' => $label,
                'line' => $name . ' said they\'d like more practice with ' . lcfirst($label) . '.',
            ];
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lessonEvidence(int $learnerId, int $orgId, int $skillId): array
    {
        /** @var LearnerSkillProgress[] $rows */
        $rows = LearnerSkillProgress::find()
            ->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => $orgId,
                'skill_id' => $skillId,
            ])
            ->with('lesson')
            ->orderBy(['recorded_at' => SORT_DESC])
            ->limit(10)
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $lesson = $row->lesson;
            $out[] = [
                'type' => 'lesson',
                'recorded_at' => $row->recorded_at,
                'rating' => $row->rating,
                'rating_label' => match ($row->rating) {
                    LearnerSkillProgress::RATING_INTRODUCED => 'Introduced',
                    LearnerSkillProgress::RATING_PRACTISING => 'Practising',
                    LearnerSkillProgress::RATING_DEVELOPING => 'Developing independence',
                    LearnerSkillProgress::RATING_CONFIDENT => 'Confident',
                    default => $row->rating,
                },
                'lesson_id' => $row->lesson_id,
                'learner_summary' => $lesson?->learner_summary,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function practiceEvidence(int $learnerId, int $orgId, string $skillCode): array
    {
        /** @var PrivatePracticeSession[] $rows */
        $rows = PrivatePracticeSession::find()
            ->andWhere(['learner_id' => $learnerId, 'organisation_id' => $orgId])
            ->orderBy(['practised_at' => SORT_DESC])
            ->limit(10)
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $codes = $row->skillCodes();
            if (!in_array($skillCode, $codes, true)) {
                continue;
            }
            $out[] = [
                'type' => 'private_practice',
                'recorded_at' => $row->practised_at,
                'note' => $row->note,
                'duration_minutes' => $row->duration_minutes,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(LearnerSkillSelfAssessment $row, ?ProgressSkill $skill): array
    {
        return [
            'id' => (int) $row->id,
            'skill_id' => (int) $row->skill_id,
            'skill_code' => $skill?->code,
            'confidence' => $row->confidence,
            'confidence_label' => LearnerSkillSelfAssessment::labelFor($row->confidence),
            'note' => $row->note,
            'recorded_at' => $row->recorded_at,
            'date_display' => gmdate('j M', strtotime($row->recorded_at . ' UTC')),
        ];
    }

    private function assertLearnerInOrg(int $learnerId, int $orgId): void
    {
        if (!Learner::find()->andWhere(['id' => $learnerId, 'organisation_id' => $orgId])->exists()) {
            throw new NotFoundHttpException('Pupil not found.');
        }
    }
}
