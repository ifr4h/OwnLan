<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Enquiry;
use app\models\Expense;
use app\models\Learner;
use app\models\Lesson;
use app\models\LessonCharge;
use app\models\MockTest;
use app\models\Organisation;
use app\models\Payment;
use yii\web\BadRequestHttpException;

/**
 * Organisation-scoped global search for instructor operations.
 *
 * Does not search private_notes, instructor_notes, or learner_summary.
 */
class GlobalSearchService
{
    private const LIMIT_PER_GROUP = 5;

    /**
     * @return array<string, mixed>
     */
    public function search(string $query, ?Organisation $org = null): array
    {
        $term = trim($query);
        if ($term === '') {
            return [
                'query' => '',
                'groups' => [],
                'quick_actions' => $this->quickActions(),
            ];
        }
        if (mb_strlen($term) < 2) {
            throw new BadRequestHttpException('Type at least 2 characters to search.');
        }

        $org = $org ?? $this->requireOrganisation();

        return [
            'query' => $term,
            'groups' => [
                ['type' => 'pupils', 'label' => 'Pupils', 'items' => $this->searchPupils($org, $term)],
                ['type' => 'lessons', 'label' => 'Lessons', 'items' => $this->searchLessons($org, $term)],
                ['type' => 'payments', 'label' => 'Payments', 'items' => $this->searchPayments($org, $term)],
                ['type' => 'expenses', 'label' => 'Expenses', 'items' => $this->searchExpenses($org, $term)],
                ['type' => 'mocks', 'label' => 'Mock tests', 'items' => $this->searchMocks($org, $term)],
                ['type' => 'enquiries', 'label' => 'Enquiries', 'items' => $this->searchEnquiries($org, $term)],
            ],
            'quick_actions' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentPupils(int $limit = 5): array
    {
        $org = $this->requireOrganisation();
        /** @var Learner[] $learners */
        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null, 'lifecycle' => Learner::LIFECYCLE_ACTIVE])
            ->orderBy(['updated_at' => SORT_DESC])
            ->limit($limit)
            ->all();

        return array_map(fn (Learner $l) => $this->pupilResult($l, $org), $learners);
    }

    /**
     * @return list<array{id: string, label: string, path: string}>
     */
    private function quickActions(): array
    {
        return [
            ['id' => 'add_lesson', 'label' => 'Add lesson', 'path' => '/lessons/new'],
            ['id' => 'add_pupil', 'label' => 'Add pupil', 'path' => '/pupils/new'],
            ['id' => 'add_expense', 'label' => 'Add expense', 'path' => '/accounts/expenses'],
            ['id' => 'today', 'label' => 'Go to Today', 'path' => '/today'],
            ['id' => 'diary', 'label' => 'Go to Diary', 'path' => '/lessons'],
            ['id' => 'accounts', 'label' => 'Go to Accounts', 'path' => '/accounts'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchPupils(Organisation $org, string $term): array
    {
        $like = $this->likePattern($term);
        /** @var Learner[] $learners */
        $learners = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->andWhere(
                'LOWER(first_name) LIKE :t
                 OR LOWER(last_name) LIKE :t
                 OR LOWER(CONCAT(first_name, \' \', last_name)) LIKE :t
                 OR LOWER(COALESCE(email, \'\')) LIKE :t
                 OR REPLACE(mobile, \' \', \'\') LIKE :mobile',
                [':t' => $like, ':mobile' => '%' . preg_replace('/\s+/', '', $term) . '%'],
            )
            ->orderBy(['last_name' => SORT_ASC, 'first_name' => SORT_ASC])
            ->limit(self::LIMIT_PER_GROUP)
            ->all();

        return array_map(fn (Learner $l) => $this->pupilResult($l, $org), $learners);
    }

    /**
     * @return array<string, mixed>
     */
    private function pupilResult(Learner $learner, Organisation $org): array
    {
        $owed = $this->owedForLearner((int) $learner->id);
        $meta = $owed > 0 ? Money::formatPence($owed) . ' due' : null;

        return [
            'id' => (int) $learner->id,
            'type' => 'pupil',
            'title' => $learner->fullName,
            'meta' => $meta,
            'path' => '/pupils/' . (int) $learner->id,
            'actions' => [
                ['label' => 'Open pupil', 'path' => '/pupils/' . (int) $learner->id],
                ['label' => 'Book lesson', 'path' => '/lessons/new?learner_id=' . (int) $learner->id],
                ['label' => 'Record payment', 'path' => '/pupils/' . (int) $learner->id . '?pay=1'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchLessons(Organisation $org, string $term): array
    {
        $like = $this->likePattern($term);
        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find(), 'l.organisation_id')
            ->alias('l')
            ->select(['l.*'])
            ->innerJoin(['learner' => Learner::tableName()], 'learner.id = l.learner_id')
            ->andWhere(
                'LOWER(learner.first_name) LIKE :t
                 OR LOWER(learner.last_name) LIKE :t
                 OR LOWER(CONCAT(learner.first_name, \' \', learner.last_name)) LIKE :t',
                [':t' => $like],
            )
            ->with(['learner'])
            ->orderBy(['l.starts_at' => SORT_DESC])
            ->limit(self::LIMIT_PER_GROUP)
            ->all();

        $rows = [];
        foreach ($lessons as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $rows[] = [
                'id' => (int) $lesson->id,
                'type' => 'lesson',
                'title' => ($lesson->learner?->fullName ?? 'Pupil') . ' · ' . $local->format('j M'),
                'meta' => $local->format('H:i') . ' · ' . ucfirst(str_replace('_', ' ', $lesson->status)),
                'path' => '/lessons/' . (int) $lesson->id,
                'actions' => [
                    ['label' => 'Open lesson', 'path' => '/lessons/' . (int) $lesson->id],
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchPayments(Organisation $org, string $term): array
    {
        $like = $this->likePattern($term);
        /** @var Payment[] $payments */
        $payments = TenantContext::scopeByOrganisation(Payment::find(), 'p.organisation_id')
            ->alias('p')
            ->innerJoin(['learner' => Learner::tableName()], 'learner.id = p.learner_id')
            ->andWhere(['p.voided_at' => null])
            ->andWhere(
                'LOWER(learner.first_name) LIKE :t
                 OR LOWER(learner.last_name) LIKE :t
                 OR LOWER(CONCAT(learner.first_name, \' \', learner.last_name)) LIKE :t',
                [':t' => $like],
            )
            ->with(['learner'])
            ->orderBy(['p.recorded_at' => SORT_DESC])
            ->limit(self::LIMIT_PER_GROUP)
            ->all();

        $rows = [];
        foreach ($payments as $payment) {
            $local = OrganisationTime::utcToLocal($payment->recorded_at, $org);
            $rows[] = [
                'id' => (int) $payment->id,
                'type' => 'payment',
                'title' => ($payment->learner?->fullName ?? 'Pupil') . ' · ' . Money::formatPence((int) $payment->amount_pence),
                'meta' => $local->format('j M Y'),
                'path' => '/pupils/' . (int) $payment->learner_id . '?pay=1',
                'actions' => [
                    ['label' => 'View pupil', 'path' => '/pupils/' . (int) $payment->learner_id],
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchExpenses(Organisation $org, string $term): array
    {
        $like = $this->likePattern($term);
        /** @var Expense[] $expenses */
        $expenses = TenantContext::scopeByOrganisation(Expense::find())
            ->andWhere(['voided_at' => null])
            ->andWhere(
                'LOWER(COALESCE(supplier, \'\')) LIKE :t
                 OR LOWER(category) LIKE :t
                 OR LOWER(COALESCE(notes, \'\')) LIKE :t',
                [':t' => $like],
            )
            ->orderBy(['spent_on' => SORT_DESC])
            ->limit(self::LIMIT_PER_GROUP)
            ->all();

        $rows = [];
        foreach ($expenses as $expense) {
            $rows[] = [
                'id' => (int) $expense->id,
                'type' => 'expense',
                'title' => Expense::categoryLabel($expense->category) . ' · ' . Money::formatPence((int) $expense->amount_pence),
                'meta' => ($expense->supplier ?: '') . ($expense->supplier ? ' · ' : '') . $expense->spent_on,
                'path' => '/accounts/expenses',
                'actions' => [
                    ['label' => 'View expenses', 'path' => '/accounts/expenses'],
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchMocks(Organisation $org, string $term): array
    {
        $like = $this->likePattern($term);
        /** @var MockTest[] $mocks */
        $mocks = TenantContext::scopeByOrganisation(MockTest::find(), 'm.organisation_id')
            ->alias('m')
            ->innerJoin(['learner' => Learner::tableName()], 'learner.id = m.learner_id')
            ->andWhere(['m.status' => MockTest::STATUS_COMPLETED])
            ->andWhere(
                'LOWER(learner.first_name) LIKE :t
                 OR LOWER(learner.last_name) LIKE :t
                 OR LOWER(CONCAT(learner.first_name, \' \', learner.last_name)) LIKE :t',
                [':t' => $like],
            )
            ->with(['learner'])
            ->orderBy(['m.finished_at' => SORT_DESC])
            ->limit(self::LIMIT_PER_GROUP)
            ->all();

        $rows = [];
        foreach ($mocks as $mock) {
            $finished = $mock->finished_at ?? $mock->started_at;
            $local = OrganisationTime::utcToLocal($finished, $org);
            $result = $mock->result === MockTest::RESULT_PASS ? 'Pass' : 'Not pass';
            $rows[] = [
                'id' => (int) $mock->id,
                'type' => 'mock',
                'title' => ($mock->learner?->fullName ?? 'Pupil') . ' · Mock test',
                'meta' => $local->format('j M Y') . ' · ' . $result,
                'path' => '/mock-tests/' . (int) $mock->id . '/review',
                'actions' => [
                    ['label' => 'Review mock', 'path' => '/mock-tests/' . (int) $mock->id . '/review'],
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchEnquiries(Organisation $org, string $term): array
    {
        $like = $this->likePattern($term);
        /** @var Enquiry[] $enquiries */
        $enquiries = TenantContext::scopeByOrganisation(Enquiry::find())
            ->andWhere(
                'LOWER(first_name) LIKE :t
                 OR LOWER(last_name) LIKE :t
                 OR LOWER(CONCAT(first_name, \' \', last_name)) LIKE :t
                 OR LOWER(COALESCE(email, \'\')) LIKE :t
                 OR REPLACE(mobile, \' \', \'\') LIKE :mobile',
                [':t' => $like, ':mobile' => '%' . preg_replace('/\s+/', '', $term) . '%'],
            )
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(self::LIMIT_PER_GROUP)
            ->all();

        $rows = [];
        foreach ($enquiries as $enquiry) {
            $rows[] = [
                'id' => (int) $enquiry->id,
                'type' => 'enquiry',
                'title' => $enquiry->getFullName(),
                'meta' => ucfirst(str_replace('_', ' ', $enquiry->status)) . ' · ' . $enquiry->postcode,
                'path' => '/pupils/enquiries/' . (int) $enquiry->id,
                'actions' => [
                    ['label' => 'Open enquiry', 'path' => '/pupils/enquiries/' . (int) $enquiry->id],
                ],
            ];
        }

        return $rows;
    }

    private function owedForLearner(int $learnerId): int
    {
        /** @var LessonCharge[] $charges */
        $charges = TenantContext::scopeByOrganisation(LessonCharge::find())
            ->andWhere(['learner_id' => $learnerId, 'status' => LessonCharge::STATUS_OUTSTANDING])
            ->all();
        $total = 0;
        foreach ($charges as $charge) {
            $total += $charge->outstandingPence();
        }

        return $total;
    }

    private function likePattern(string $term): string
    {
        return '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)) . '%';
    }

    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new \yii\web\NotFoundHttpException('Organisation not found.');
        }

        return $org;
    }
}
