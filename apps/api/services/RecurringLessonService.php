<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\Learner;
use app\models\Lesson;
use app\models\LessonSeries;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Weekly recurring lessons only — preview, create, this/future edit & cancel.
 */
class RecurringLessonService
{
    private LessonService $lessons;

    public function __construct(?LessonService $lessons = null)
    {
        $this->lessons = $lessons ?? new LessonService();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function preview(array $data): array
    {
        $org = $this->requireOrganisation();
        $plan = $this->planOccurrences($org, $data);

        return [
            'count' => count($plan['occurrences']),
            'conflict_count' => count(array_filter($plan['occurrences'], static fn (array $o) => $o['has_conflict'])),
            'occurrences' => $plan['occurrences'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $org = $this->requireOrganisation();
        $instructor = $this->requireCurrentInstructor($org);
        $plan = $this->planOccurrences($org, $data);

        if ($plan['occurrences'] === []) {
            throw new BadRequestHttpException('No lessons would be created with these settings.');
        }

        $hardConflicts = array_filter($plan['occurrences'], static fn (array $o) => $o['has_conflict']);
        if ($hardConflicts !== [] && empty($data['allow_conflicts'])) {
            throw new BadRequestHttpException(
                'Some dates conflict with existing lessons. Review the preview or allow conflicts.',
            );
        }

        $learner = $this->findLearnerOwned((int) $data['learner_id']);
        $now = gmdate('Y-m-d H:i:s');

        $series = new LessonSeries();
        $series->organisation_id = (int) $org->id;
        $series->instructor_id = (int) $instructor->id;
        $series->learner_id = (int) $learner->id;
        $series->duration_minutes = $plan['duration_minutes'];
        $series->pickup_address = $plan['pickup_address'];
        $series->anchor_starts_at_local = $plan['anchor_local'];
        $series->until_date = $plan['until_date'];
        $series->occurrence_count = $plan['occurrence_count'];
        $series->created_at = $now;
        $series->updated_at = $now;

        $created = [];
        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$series->save()) {
                throw new BadRequestHttpException($this->firstError($series));
            }

            foreach ($plan['occurrences'] as $slot) {
                $lesson = new Lesson();
                $lesson->organisation_id = (int) $org->id;
                $lesson->instructor_id = (int) $instructor->id;
                $lesson->learner_id = (int) $learner->id;
                $lesson->series_id = (int) $series->id;
                $lesson->status = Lesson::STATUS_SCHEDULED;
                $lesson->duration_minutes = $plan['duration_minutes'];
                $lesson->pickup_address = $plan['pickup_address'];
                $lesson->starts_at = $slot['starts_at_utc'];
                $lesson->created_at = $now;
                $lesson->updated_at = $now;
                if (!$lesson->save()) {
                    throw new BadRequestHttpException($this->firstError($lesson));
                }
                $lesson->populateRelation('learner', $learner);
                $lesson->populateRelation('instructor', $instructor);
                $created[] = $this->lessons->toApiArray($lesson, $org);
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'series_id' => (int) $series->id,
            'count' => count($created),
            'items' => $created,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateOccurrence(int $id, array $data, string $scope): array
    {
        $org = $this->requireOrganisation();
        $lesson = $this->findOwnedLesson($id);
        $scope = $this->normalizeScope($scope);

        if ($scope === 'this' || $lesson->series_id === null) {
            return $this->lessons->update($id, $data);
        }

        if (!$lesson->isScheduled()) {
            throw new BadRequestHttpException('Only scheduled lessons can be edited.');
        }

        $targets = $this->futureScheduledInSeries((int) $lesson->series_id, $lesson->starts_at);
        $deltaSeconds = null;
        if (array_key_exists('starts_at_local', $data)) {
            $newUtc = OrganisationTime::localToUtc((string) $data['starts_at_local'], $org);
            $oldUtc = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            $deltaSeconds = $newUtc->getTimestamp() - $oldUtc->getTimestamp();
        }

        $results = [];
        $tx = Yii::$app->db->beginTransaction();
        try {
            foreach ($targets as $target) {
                $payload = $data;
                if ($deltaSeconds !== null) {
                    $shifted = (new DateTimeImmutable($target->starts_at, new DateTimeZone('UTC')))
                        ->modify(($deltaSeconds >= 0 ? '+' : '') . $deltaSeconds . ' seconds');
                    $payload['starts_at_local'] = OrganisationTime::formatLocalIso(
                        OrganisationTime::utcToLocal($shifted, $org),
                    );
                }
                $results[] = $this->lessons->update((int) $target->id, $payload);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'scope' => 'this_and_future',
            'count' => count($results),
            'items' => $results,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelOccurrence(int $id, string $scope, array $data = []): array
    {
        $lesson = $this->findOwnedLesson($id);
        $scope = $this->normalizeScope($scope);

        if ($scope === 'this' || $lesson->series_id === null) {
            return $this->lessons->cancel($id, $data);
        }

        $targets = $this->futureScheduledInSeries((int) $lesson->series_id, $lesson->starts_at);
        $results = [];
        $tx = Yii::$app->db->beginTransaction();
        try {
            foreach ($targets as $target) {
                $results[] = $this->lessons->cancel((int) $target->id, $data);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'scope' => 'this_and_future',
            'count' => count($results),
            'items' => $results,
            // Recover the occurrence the instructor cancelled (first in the batch).
            'empty_seat' => $results[0]['empty_seat'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *   duration_minutes: int,
     *   pickup_address: string|null,
     *   anchor_local: string,
     *   until_date: string|null,
     *   occurrence_count: int|null,
     *   occurrences: list<array<string, mixed>>
     * }
     */
    private function planOccurrences(Organisation $org, array $data): array
    {
        $learnerId = (int) ($data['learner_id'] ?? 0);
        $learner = $this->findLearnerOwned($learnerId);

        $anchorLocal = trim((string) ($data['starts_at_local'] ?? ''));
        if ($anchorLocal === '') {
            throw new BadRequestHttpException('Choose the first lesson date and time.');
        }

        $duration = $this->resolveDuration($data['duration_minutes'] ?? null);
        $pickup = $this->resolvePickup($data['pickup_address'] ?? null, $learner);

        $untilDate = null;
        $count = null;
        if (!empty($data['until_date'])) {
            $untilDate = $this->parseDate((string) $data['until_date']);
        } elseif (isset($data['occurrence_count']) && $data['occurrence_count'] !== '') {
            $count = (int) $data['occurrence_count'];
            if ($count < 2 || $count > LessonSeries::MAX_OCCURRENCES) {
                throw new BadRequestHttpException(
                    'Number of lessons must be between 2 and ' . LessonSeries::MAX_OCCURRENCES . '.',
                );
            }
        } else {
            throw new BadRequestHttpException('Choose an end date or a number of weekly lessons.');
        }

        $anchorUtc = OrganisationTime::localToUtc($anchorLocal, $org);
        $anchorLocalDt = OrganisationTime::utcToLocal($anchorUtc, $org);
        $normalizedAnchor = OrganisationTime::formatLocalIso($anchorLocalDt);

        $slots = [];
        $cursor = $anchorLocalDt;
        $i = 0;
        while ($i < LessonSeries::MAX_OCCURRENCES) {
            if ($count !== null && $i >= $count) {
                break;
            }
            if ($untilDate !== null && $cursor->format('Y-m-d') > $untilDate) {
                break;
            }

            $startUtc = $cursor->setTimezone(new DateTimeZone('UTC'));
            $endUtc = $startUtc->modify('+' . $duration . ' minutes');
            $conflicts = $this->findConflicts($org, $startUtc, $endUtc, null);

            $slots[] = [
                'starts_at_local' => OrganisationTime::formatLocalIso($cursor),
                'starts_at_display' => OrganisationTime::formatLocalDisplay($cursor),
                'starts_at_utc' => OrganisationTime::formatUtc($startUtc),
                'ends_at_local' => OrganisationTime::formatLocalIso(
                    $cursor->modify('+' . $duration . ' minutes'),
                ),
                'has_conflict' => $conflicts !== [],
                'conflicts' => $conflicts,
            ];

            $i++;
            $cursor = $anchorLocalDt->modify('+' . $i . ' weeks');
        }

        if ($untilDate !== null && $slots === []) {
            throw new BadRequestHttpException('End date is before the first lesson.');
        }

        return [
            'duration_minutes' => $duration,
            'pickup_address' => $pickup,
            'anchor_local' => $normalizedAnchor,
            'until_date' => $untilDate,
            'occurrence_count' => $count,
            'occurrences' => $slots,
        ];
    }

    /**
     * @return list<array{id: int, learner_name: string|null, starts_at_display: string}>
     */
    private function findConflicts(
        Organisation $org,
        DateTimeImmutable $startUtc,
        DateTimeImmutable $endUtc,
        ?int $excludeLessonId,
    ): array {
        /** @var Lesson[] $candidates */
        $candidates = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['<', 'starts_at', OrganisationTime::formatUtc($endUtc)])
            ->with(['learner'])
            ->all();

        $hits = [];
        foreach ($candidates as $lesson) {
            if ($excludeLessonId !== null && (int) $lesson->id === $excludeLessonId) {
                continue;
            }
            $otherStart = new DateTimeImmutable($lesson->starts_at, new DateTimeZone('UTC'));
            $otherEnd = $otherStart->modify('+' . (int) $lesson->duration_minutes . ' minutes');
            if ($startUtc < $otherEnd && $otherStart < $endUtc) {
                $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
                $hits[] = [
                    'id' => (int) $lesson->id,
                    'learner_name' => $lesson->learner?->fullName,
                    'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
                ];
            }
        }

        return $hits;
    }

    /**
     * @return list<Lesson>
     */
    private function futureScheduledInSeries(int $seriesId, string $fromStartsAtUtc): array
    {
        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere([
                'series_id' => $seriesId,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>=', 'starts_at', $fromStartsAtUtc])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        return $lessons;
    }

    private function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));
        if ($scope === '' || $scope === 'one') {
            $scope = 'this';
        }
        if ($scope === 'future') {
            $scope = 'this_and_future';
        }
        if (!in_array($scope, ['this', 'this_and_future'], true)) {
            throw new BadRequestHttpException('Scope must be this or this_and_future.');
        }

        return $scope;
    }

    private function parseDate(string $date): string
    {
        $date = trim($date);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dt === false || $dt->format('Y-m-d') !== $date) {
            throw new BadRequestHttpException('End date must be YYYY-MM-DD.');
        }

        return $date;
    }

    private function resolveDuration(mixed $value): int
    {
        if ($value === null || $value === '') {
            return $this->requireOrganisation()->defaultLessonDurationMinutes();
        }
        $minutes = (int) $value;
        if ($minutes < 15 || $minutes > 480) {
            throw new BadRequestHttpException('Duration must be between 15 and 480 minutes.');
        }

        return $minutes;
    }

    private function resolvePickup(mixed $value, Learner $learner): ?string
    {
        if ($value === null) {
            return $learner->default_pickup_address;
        }
        $text = trim((string) $value);

        return $text === '' ? $learner->default_pickup_address : $text;
    }

    private function findOwnedLesson(int $id): Lesson
    {
        /** @var Lesson|null $lesson */
        $lesson = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['id' => $id])
            ->one();
        if ($lesson === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        return $lesson;
    }

    private function findLearnerOwned(int $learnerId): Learner
    {
        if ($learnerId < 1) {
            throw new BadRequestHttpException('Choose a pupil.');
        }
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }
        if ($learner->isArchived) {
            throw new BadRequestHttpException('This pupil is archived.');
        }

        return $learner;
    }

    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }

    private function requireCurrentInstructor(Organisation $organisation): Instructor
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }
        $instructor = Instructor::find()
            ->where([
                'organisation_id' => (int) $organisation->id,
                'user_id' => (int) Yii::$app->user->id,
            ])
            ->one();
        if ($instructor === null) {
            throw new BadRequestHttpException('No instructor profile for this account.');
        }

        return $instructor;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors ? (string) reset($errors) : 'Unable to save.';
    }
}
