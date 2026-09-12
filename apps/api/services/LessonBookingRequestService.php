<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\PortalContext;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\Learner;
use app\models\Lesson;
use app\models\LessonBookingRequest;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Learner booking requests, instant booking, reschedule and cancellation.
 */
class LessonBookingRequestService
{
    private LearnerBookingAvailabilityService $availability;

    public function __construct(
        ?LearnerBookingAvailabilityService $availability = null,
    ) {
        $this->availability = $availability ?? new LearnerBookingAvailabilityService();
    }

    /**
     * @return array<string, mixed>
     */
    public function portalSettings(): array
    {
        [$learner, $org] = $this->requirePortalLearnerOrg();
        $mode = $org->bookingMode();
        $paymentPolicy = $org->booking_payment_policy ?? 'none';
        $requiresPayment = $paymentPolicy === 'require_to_confirm'
            && $mode === Organisation::BOOKING_MODE_INSTANT;

        return [
            'booking_mode' => $mode,
            'reschedule_mode' => $org->learnerRescheduleMode(),
            'can_book' => $mode !== Organisation::BOOKING_MODE_MANUAL,
            'can_request' => $mode === Organisation::BOOKING_MODE_REQUEST,
            'can_instant_book' => $mode === Organisation::BOOKING_MODE_INSTANT,
            'can_cancel' => $org->learnerCanCancel(),
            'cancellation_policy' => $org->cancellation_policy,
            'cancellation_notice_hours' => $org->cancellationNoticeHours(),
            'cancellation_late_policy' => $org->cancellationLatePolicy(),
            'booking_payment_policy' => $paymentPolicy,
            'requires_payment_to_confirm' => $requiresPayment,
            'learner_id' => (int) $learner->id,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForPortal(): array
    {
        [$learner, $org] = $this->requirePortalLearnerOrg();
        $this->expireStaleForOrganisation((int) $org->id);

        /** @var LessonBookingRequest[] $requests */
        $requests = PortalContext::scopeOwnLearner(LessonBookingRequest::find())
            ->andWhere(['not in', 'status', [
                LessonBookingRequest::STATUS_ACCEPTED,
                LessonBookingRequest::STATUS_DECLINED,
                LessonBookingRequest::STATUS_WITHDRAWN,
                LessonBookingRequest::STATUS_EXPIRED,
            ]])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(20)
            ->all();

        return array_map(
            fn (LessonBookingRequest $r) => $this->serializeRequest($r, $org),
            $requests,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingForInstructor(): array
    {
        $org = $this->requireOrganisation();
        $this->expireStaleForOrganisation((int) $org->id);

        /** @var LessonBookingRequest[] $requests */
        $requests = TenantContext::scopeByOrganisation(LessonBookingRequest::find())
            ->andWhere(['status' => [
                LessonBookingRequest::STATUS_PENDING,
                LessonBookingRequest::STATUS_COUNTER_PROPOSED,
            ]])
            ->with(['learner'])
            ->orderBy(['requested_starts_at' => SORT_ASC])
            ->limit(30)
            ->all();

        return array_map(
            fn (LessonBookingRequest $r) => $this->serializeRequest($r, $org, true),
            $requests,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createFromPortal(array $data): array
    {
        [$learner, $org, $instructor] = $this->requirePortalContext();
        if ($instructor === null) {
            throw new NotFoundHttpException('Instructor not found.');
        }

        $mutationId = $this->normalizeMutationId($data['client_mutation_id'] ?? null);
        if ($mutationId !== null) {
            $existing = $this->findByMutation((int) $org->id, $mutationId);
            if ($existing !== null) {
                return $this->wrapResult($existing, $org);
            }
        }

        $type = strtolower(trim((string) ($data['type'] ?? LessonBookingRequest::TYPE_BOOK)));
        if ($type === LessonBookingRequest::TYPE_RESCHEDULE) {
            return $this->createRescheduleFromPortal($learner, $org, $instructor, $data, $mutationId);
        }

        return $this->createBookFromPortal($learner, $org, $instructor, $data, $mutationId);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function accept(int $id, array $data = []): array
    {
        $org = $this->requireOrganisation();
        $request = $this->findOwned($id);
        if (!$request->isOpen() || $request->status === LessonBookingRequest::STATUS_COUNTER_PROPOSED) {
            throw new BadRequestHttpException('This request cannot be accepted in its current state.');
        }

        $learner = $this->findLearnerOwned((int) $request->learner_id);
        $instructor = $this->findInstructorOwned((int) $request->instructor_id);
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $startUtc = new DateTimeImmutable($request->requested_starts_at, new DateTimeZone('UTC'));

        $tx = Yii::$app->db->beginTransaction();
        try {
            $this->availability->lockAndAssertNoOverlap(
                $org,
                (int) $instructor->id,
                $startUtc,
                (int) $request->duration_minutes,
                $request->type === LessonBookingRequest::TYPE_RESCHEDULE
                    ? (int) $request->original_lesson_id
                    : null,
            );
            $this->availability->assertSlotAvailable(
                $org,
                $instructor,
                $learner,
                $startUtc,
                (int) $request->duration_minutes,
                $request->pickup_address,
                $request->type === LessonBookingRequest::TYPE_RESCHEDULE
                    ? (int) $request->original_lesson_id
                    : null,
                $nowUtc,
            );

            $lesson = $this->promoteToLesson($request, $org, $learner, $startUtc);
            $now = gmdate('Y-m-d H:i:s');
            $request->status = LessonBookingRequest::STATUS_ACCEPTED;
            $request->lesson_id = (int) $lesson->id;
            $request->responded_at = $now;
            $request->updated_at = $now;
            if (!$request->save()) {
                throw new BadRequestHttpException($this->firstError($request));
            }

            if ($request->type === LessonBookingRequest::TYPE_RESCHEDULE && $request->original_lesson_id !== null) {
                $this->cancelOriginalForReschedule((int) $request->original_lesson_id, $org);
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return $this->serializeRequest($request->refresh(), $org, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decline(int $id, array $data = []): array
    {
        $org = $this->requireOrganisation();
        $request = $this->findOwned($id);
        if (!$request->isOpen()) {
            throw new BadRequestHttpException('This request is no longer open.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $request->status = LessonBookingRequest::STATUS_DECLINED;
        $request->decline_reason = trim((string) ($data['reason'] ?? '')) ?: null;
        $request->responded_at = $now;
        $request->updated_at = $now;
        if (!$request->save()) {
            throw new BadRequestHttpException($this->firstError($request));
        }

        return $this->serializeRequest($request, $org, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function suggestAlternative(int $id, array $data): array
    {
        $org = $this->requireOrganisation();
        $request = $this->findOwned($id);
        if ($request->status !== LessonBookingRequest::STATUS_PENDING) {
            throw new BadRequestHttpException('Only pending requests can be counter-proposed.');
        }

        $startsLocal = trim((string) ($data['starts_at_local'] ?? ''));
        if ($startsLocal === '') {
            throw new BadRequestHttpException('Choose a time to suggest.');
        }

        $learner = $this->findLearnerOwned((int) $request->learner_id);
        $instructor = $this->findInstructorOwned((int) $request->instructor_id);
        $startUtc = OrganisationTime::localToUtc($startsLocal, $org);
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->availability->assertSlotAvailable(
            $org,
            $instructor,
            $learner,
            $startUtc,
            (int) $request->duration_minutes,
            $request->pickup_address,
            $request->type === LessonBookingRequest::TYPE_RESCHEDULE
                ? (int) $request->original_lesson_id
                : null,
            $nowUtc,
        );

        $now = gmdate('Y-m-d H:i:s');
        $request->status = LessonBookingRequest::STATUS_COUNTER_PROPOSED;
        $request->suggested_starts_at = OrganisationTime::formatUtc($startUtc);
        $request->updated_at = $now;
        if (!$request->save()) {
            throw new BadRequestHttpException($this->firstError($request));
        }

        return $this->serializeRequest($request, $org, true);
    }

    public function withdrawFromPortal(int $id): array
    {
        [$learner, $org] = $this->requirePortalLearnerOrg();
        /** @var LessonBookingRequest|null $request */
        $request = PortalContext::scopeOwnLearner(LessonBookingRequest::find())
            ->andWhere(['id' => $id])
            ->one();
        if ($request === null) {
            throw new NotFoundHttpException('Request not found.');
        }
        if (!$request->isOpen()) {
            throw new BadRequestHttpException('This request is no longer open.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $request->status = LessonBookingRequest::STATUS_WITHDRAWN;
        $request->responded_at = $now;
        $request->updated_at = $now;
        if (!$request->save()) {
            throw new BadRequestHttpException($this->firstError($request));
        }

        return $this->serializeRequest($request, $org);
    }

    public function acceptCounterFromPortal(int $id): array
    {
        [$learner, $org, $instructor] = $this->requirePortalContext();
        if ($instructor === null) {
            throw new NotFoundHttpException('Instructor not found.');
        }

        /** @var LessonBookingRequest|null $request */
        $request = PortalContext::scopeOwnLearner(LessonBookingRequest::find())
            ->andWhere(['id' => $id])
            ->one();
        if ($request === null) {
            throw new NotFoundHttpException('Request not found.');
        }
        if ($request->status !== LessonBookingRequest::STATUS_COUNTER_PROPOSED) {
            throw new BadRequestHttpException('No alternative time to accept.');
        }
        if ($request->suggested_starts_at === null) {
            throw new BadRequestHttpException('No alternative time to accept.');
        }

        $startUtc = new DateTimeImmutable($request->suggested_starts_at, new DateTimeZone('UTC'));
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $tx = Yii::$app->db->beginTransaction();
        try {
            $this->availability->lockAndAssertNoOverlap(
                $org,
                (int) $instructor->id,
                $startUtc,
                (int) $request->duration_minutes,
                $request->type === LessonBookingRequest::TYPE_RESCHEDULE
                    ? (int) $request->original_lesson_id
                    : null,
            );
            $this->availability->assertSlotAvailable(
                $org,
                $instructor,
                $learner,
                $startUtc,
                (int) $request->duration_minutes,
                $request->pickup_address,
                $request->type === LessonBookingRequest::TYPE_RESCHEDULE
                    ? (int) $request->original_lesson_id
                    : null,
                $nowUtc,
            );

            $request->requested_starts_at = OrganisationTime::formatUtc($startUtc);
            $lesson = $this->promoteToLesson($request, $org, $learner, $startUtc);
            $now = gmdate('Y-m-d H:i:s');
            $request->status = LessonBookingRequest::STATUS_ACCEPTED;
            $request->lesson_id = (int) $lesson->id;
            $request->responded_at = $now;
            $request->updated_at = $now;
            if (!$request->save()) {
                throw new BadRequestHttpException($this->firstError($request));
            }

            if ($request->type === LessonBookingRequest::TYPE_RESCHEDULE && $request->original_lesson_id !== null) {
                $this->cancelOriginalForReschedule((int) $request->original_lesson_id, $org);
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return $this->wrapResult($request, $org);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function cancelLessonFromPortal(int $lessonId, array $data = []): array
    {
        [$learner, $org] = $this->requirePortalLearnerOrg();
        if (!$org->learnerCanCancel()) {
            throw new ForbiddenHttpException('Your instructor handles cancellations directly.');
        }

        /** @var Lesson|null $lesson */
        $lesson = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['id' => $lessonId, 'status' => Lesson::STATUS_SCHEDULED])
            ->one();
        if ($lesson === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        $policy = new CancellationPolicyService();
        $preview = $policy->previewForLearner($lesson, $org);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($preview['reason_required'] && $reason === '') {
            throw new BadRequestHttpException('Please say why you’re cancelling with short notice.');
        }
        if (mb_strlen($reason) > 500) {
            throw new BadRequestHttpException('Keep the reason under 500 characters.');
        }

        $settle = $policy->settlePayloadForOutcome($preview['outcome']);
        $payload = $settle ?? [];
        $payload['cancelled_by'] = Lesson::CANCELLED_BY_LEARNER;
        $payload['cancellation_notice_hours'] = $policy->noticeHoursFromEvaluation($preview);
        if ($reason !== '') {
            $payload['cancellation_reason'] = $reason;
        }

        $result = $this->cancelLessonDirect($lesson, $org, $payload);
        $result['cancellation'] = $preview;

        return $result;
    }

    public function expireStaleForOrganisation(int $organisationId): int
    {
        $now = gmdate('Y-m-d H:i:s');

        return Yii::$app->db->createCommand()->update(
            '{{%lesson_booking_requests}}',
            [
                'status' => LessonBookingRequest::STATUS_EXPIRED,
                'updated_at' => $now,
                'responded_at' => $now,
            ],
            [
                'and',
                ['organisation_id' => $organisationId],
                ['status' => [
                    LessonBookingRequest::STATUS_PENDING,
                    LessonBookingRequest::STATUS_COUNTER_PROPOSED,
                ]],
                ['<', 'requested_starts_at', $now],
            ],
        )->execute();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createBookFromPortal(
        Learner $learner,
        Organisation $org,
        Instructor $instructor,
        array $data,
        ?string $mutationId,
    ): array {
        $mode = $org->bookingMode();
        if ($mode === Organisation::BOOKING_MODE_MANUAL) {
            throw new ForbiddenHttpException('Your instructor arranges lessons directly.');
        }

        [$startUtc, $duration, $pickup] = $this->parseBookingPayload($learner, $org, $data);
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->availability->assertSlotAvailable(
            $org,
            $instructor,
            $learner,
            $startUtc,
            $duration,
            $pickup,
            null,
            $nowUtc,
        );

        if ($mode === Organisation::BOOKING_MODE_INSTANT) {
            return $this->instantBook(
                $learner,
                $org,
                $instructor,
                $startUtc,
                $duration,
                $pickup,
                LessonBookingRequest::TYPE_BOOK,
                null,
                $mutationId,
            );
        }

        return $this->createPendingRequest(
            $learner,
            $org,
            $instructor,
            LessonBookingRequest::TYPE_BOOK,
            null,
            $startUtc,
            $duration,
            $pickup,
            $mutationId,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRescheduleFromPortal(
        Learner $learner,
        Organisation $org,
        Instructor $instructor,
        array $data,
        ?string $mutationId,
    ): array {
        $mode = $org->learnerRescheduleMode();
        if ($mode === Organisation::BOOKING_MODE_MANUAL) {
            throw new ForbiddenHttpException('Ask your instructor to reschedule this lesson.');
        }

        $lessonId = (int) ($data['lesson_id'] ?? $data['original_lesson_id'] ?? 0);
        if ($lessonId < 1) {
            throw new BadRequestHttpException('Lesson is required.');
        }

        /** @var Lesson|null $original */
        $original = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['id' => $lessonId, 'status' => Lesson::STATUS_SCHEDULED])
            ->one();
        if ($original === null) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        [$startUtc, $duration, $pickup] = $this->parseBookingPayload($learner, $org, $data);
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->availability->assertSlotAvailable(
            $org,
            $instructor,
            $learner,
            $startUtc,
            $duration,
            $pickup,
            (int) $original->id,
            $nowUtc,
        );

        if ($mode === Organisation::BOOKING_MODE_INSTANT) {
            return $this->instantReschedule(
                $learner,
                $org,
                $instructor,
                $original,
                $startUtc,
                $duration,
                $pickup,
                $mutationId,
            );
        }

        return $this->createPendingRequest(
            $learner,
            $org,
            $instructor,
            LessonBookingRequest::TYPE_RESCHEDULE,
            (int) $original->id,
            $startUtc,
            $duration,
            $pickup,
            $mutationId,
        );
    }

    /**
     * @return array{0: DateTimeImmutable, 1: int, 2: string|null}
     */
    private function parseBookingPayload(Learner $learner, Organisation $org, array $data): array
    {
        $startsLocal = trim((string) ($data['starts_at_local'] ?? ''));
        if ($startsLocal === '') {
            throw new BadRequestHttpException('Choose a lesson time.');
        }
        $startUtc = OrganisationTime::localToUtc($startsLocal, $org);

        $duration = isset($data['duration_minutes'])
            ? (int) $data['duration_minutes']
            : $org->defaultLessonDurationMinutes();

        $pickup = isset($data['pickup_address']) ? trim((string) $data['pickup_address']) : null;
        if ($pickup === '') {
            $pickup = $learner->default_pickup_address;
        }

        return [$startUtc, $duration, $pickup];
    }

    /**
     * @return array<string, mixed>
     */
    private function instantBook(
        Learner $learner,
        Organisation $org,
        Instructor $instructor,
        DateTimeImmutable $startUtc,
        int $duration,
        ?string $pickup,
        string $type,
        ?int $originalLessonId,
        ?string $mutationId,
    ): array {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $this->availability->lockAndAssertNoOverlap(
                $org,
                (int) $instructor->id,
                $startUtc,
                $duration,
                $originalLessonId,
            );

            $lesson = $this->createLessonDirect(
                $org,
                $instructor,
                $learner,
                $startUtc,
                $duration,
                $pickup,
            );

            $now = gmdate('Y-m-d H:i:s');
            $request = new LessonBookingRequest();
            $request->organisation_id = (int) $org->id;
            $request->instructor_id = (int) $instructor->id;
            $request->learner_id = (int) $learner->id;
            $request->type = $type;
            $request->original_lesson_id = $originalLessonId;
            $request->requested_starts_at = OrganisationTime::formatUtc($startUtc);
            $request->duration_minutes = $duration;
            $request->pickup_address = $pickup;
            $request->status = LessonBookingRequest::STATUS_ACCEPTED;
            $request->lesson_id = (int) $lesson->id;
            $request->client_mutation_id = $mutationId;
            $request->created_at = $now;
            $request->updated_at = $now;
            $request->responded_at = $now;
            if (!$request->save()) {
                throw new BadRequestHttpException($this->firstError($request));
            }

            if ($type === LessonBookingRequest::TYPE_RESCHEDULE && $originalLessonId !== null) {
                $this->cancelLessonDirectById($originalLessonId, $org);
            }

            $tx->commit();
        } catch (BadRequestHttpException $e) {
            $tx->rollBack();
            if (str_contains($e->getMessage(), 'just been taken')) {
                throw new ConflictHttpException($e->getMessage());
            }
            throw $e;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $lessonPayload = $this->serializeLesson($lesson, $org, $learner);

        return [
            'outcome' => 'booked',
            'request' => $this->serializeRequest($request, $org),
            'lesson' => $lessonPayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instantReschedule(
        Learner $learner,
        Organisation $org,
        Instructor $instructor,
        Lesson $original,
        DateTimeImmutable $startUtc,
        int $duration,
        ?string $pickup,
        ?string $mutationId,
    ): array {
        return $this->instantBook(
            $learner,
            $org,
            $instructor,
            $startUtc,
            $duration,
            $pickup,
            LessonBookingRequest::TYPE_RESCHEDULE,
            (int) $original->id,
            $mutationId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createPendingRequest(
        Learner $learner,
        Organisation $org,
        Instructor $instructor,
        string $type,
        ?int $originalLessonId,
        DateTimeImmutable $startUtc,
        int $duration,
        ?string $pickup,
        ?string $mutationId,
    ): array {
        $now = gmdate('Y-m-d H:i:s');
        $request = new LessonBookingRequest();
        $request->organisation_id = (int) $org->id;
        $request->instructor_id = (int) $instructor->id;
        $request->learner_id = (int) $learner->id;
        $request->type = $type;
        $request->original_lesson_id = $originalLessonId;
        $request->requested_starts_at = OrganisationTime::formatUtc($startUtc);
        $request->duration_minutes = $duration;
        $request->pickup_address = $pickup;
        $request->status = LessonBookingRequest::STATUS_PENDING;
        $request->client_mutation_id = $mutationId;
        $request->created_at = $now;
        $request->updated_at = $now;
        $request->expires_at = $request->requested_starts_at;
        if (!$request->save()) {
            throw new BadRequestHttpException($this->firstError($request));
        }

        return $this->wrapResult($request, $org);
    }

    private function promoteToLesson(
        LessonBookingRequest $request,
        Organisation $org,
        Learner $learner,
        DateTimeImmutable $startUtc,
    ): Lesson {
        $instructor = $this->findInstructorOwned((int) $request->instructor_id);

        return $this->createLessonDirect(
            $org,
            $instructor,
            $learner,
            $startUtc,
            (int) $request->duration_minutes,
            $request->pickup_address,
        );
    }

    private function cancelOriginalForReschedule(int $lessonId, Organisation $org): void
    {
        $this->cancelLessonDirectById($lessonId, $org);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function cancelLessonDirect(Lesson $lesson, Organisation $org, array $data = []): array
    {
        if ($lesson->status === Lesson::STATUS_CANCELLED) {
            throw new BadRequestHttpException('This lesson is already cancelled.');
        }
        if ($lesson->status === Lesson::STATUS_COMPLETED) {
            throw new BadRequestHttpException('Completed lessons cannot be cancelled.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $lesson->status = Lesson::STATUS_CANCELLED;
        $lesson->cancelled_at = $now;
        $lesson->updated_at = $now;
        $cancelledBy = trim((string) ($data['cancelled_by'] ?? ''));
        if (in_array($cancelledBy, [
            Lesson::CANCELLED_BY_INSTRUCTOR,
            Lesson::CANCELLED_BY_LEARNER,
            Lesson::CANCELLED_BY_SYSTEM,
        ], true)) {
            $lesson->cancelled_by = $cancelledBy;
        } elseif ($lesson->cancelled_by === null || $lesson->cancelled_by === '') {
            $lesson->cancelled_by = Lesson::CANCELLED_BY_SYSTEM;
        }
        $reason = trim((string) ($data['cancellation_reason'] ?? ''));
        if ($reason !== '') {
            $lesson->cancellation_reason = mb_substr($reason, 0, 500);
        }
        $policy = new CancellationPolicyService();
        $noticeHours = $policy->noticeHoursFromPayload($data);
        if ($noticeHours !== null) {
            $lesson->cancellation_notice_hours = $noticeHours;
        }

        $finance = null;
        $tx = Yii::$app->db->beginTransaction();
        try {
            $attrs = ['status', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'updated_at'];
            if ($noticeHours !== null) {
                $attrs[] = 'cancellation_notice_hours';
            }
            if (!$lesson->save(true, $attrs)) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }
            if (array_key_exists('charge', $data)) {
                $finance = (new FinanceService())->settleCancelledLesson($lesson, $org, $data);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $lesson->refresh();
        $payload = $this->serializeLesson($lesson, $org, $lesson->learner);
        if ($finance !== null) {
            $payload['finance'] = $finance;
        }

        return $payload;
    }

    private function cancelLessonDirectById(int $lessonId, Organisation $org): void
    {
        /** @var Lesson|null $lesson */
        $lesson = Lesson::findOne([
            'id' => $lessonId,
            'organisation_id' => (int) $org->id,
            'status' => Lesson::STATUS_SCHEDULED,
        ]);
        if ($lesson === null) {
            return;
        }
        $this->cancelLessonDirect($lesson, $org, [
            'source' => 'learner_reschedule',
            'charge' => 'waived',
            'cancelled_by' => Lesson::CANCELLED_BY_SYSTEM,
        ]);
    }

    private function createLessonDirect(
        Organisation $org,
        Instructor $instructor,
        Learner $learner,
        DateTimeImmutable $startUtc,
        int $durationMinutes,
        ?string $pickup,
    ): Lesson {
        $lesson = new Lesson();
        $lesson->organisation_id = (int) $org->id;
        $lesson->instructor_id = (int) $instructor->id;
        $lesson->learner_id = (int) $learner->id;
        $lesson->status = Lesson::STATUS_SCHEDULED;
        $lesson->duration_minutes = $durationMinutes;
        $lesson->starts_at = OrganisationTime::formatUtc($startUtc);
        $lesson->pickup_address = $pickup !== null && trim($pickup) !== ''
            ? trim($pickup)
            : $learner->default_pickup_address;
        $now = gmdate('Y-m-d H:i:s');
        $lesson->created_at = $now;
        $lesson->updated_at = $now;
        if (!$lesson->save()) {
            throw new BadRequestHttpException($this->firstError($lesson));
        }

        return $lesson;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLesson(Lesson $lesson, Organisation $org, ?Learner $learner): array
    {
        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
        $ends = $local->modify('+' . (int) $lesson->duration_minutes . ' minutes');

        return [
            'id' => (int) $lesson->id,
            'learner_id' => (int) $lesson->learner_id,
            'learner_name' => $learner?->fullName,
            'starts_at' => $lesson->starts_at,
            'starts_at_local' => OrganisationTime::formatLocalIso($local),
            'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
            'starts_at_day' => $local->format('D j M'),
            'starts_at_time' => $local->format('H:i'),
            'ends_at_time' => $ends->format('H:i'),
            'duration_minutes' => (int) $lesson->duration_minutes,
            'pickup_address' => $lesson->pickup_address,
            'status' => $lesson->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wrapResult(LessonBookingRequest $request, Organisation $org): array
    {
        $serialized = $this->serializeRequest($request, $org);
        if ($request->status === LessonBookingRequest::STATUS_ACCEPTED) {
            return [
                'outcome' => 'booked',
                'request' => $serialized,
            ];
        }

        return [
            'outcome' => 'requested',
            'request' => $serialized,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRequest(
        LessonBookingRequest $request,
        Organisation $org,
        bool $includeLearner = false,
    ): array {
        $startLocal = OrganisationTime::utcToLocal($request->requested_starts_at, $org);
        $endsLocal = $startLocal->modify('+' . (int) $request->duration_minutes . ' minutes');

        $payload = [
            'id' => (int) $request->id,
            'type' => $request->type,
            'status' => $request->status,
            'original_lesson_id' => $request->original_lesson_id !== null
                ? (int) $request->original_lesson_id
                : null,
            'lesson_id' => $request->lesson_id !== null ? (int) $request->lesson_id : null,
            'starts_at_local' => OrganisationTime::formatLocalIso($startLocal),
            'starts_at_display' => OrganisationTime::formatLocalDisplay($startLocal),
            'starts_at_day' => $startLocal->format('D j M'),
            'starts_at_time' => $startLocal->format('H:i'),
            'ends_at_time' => $endsLocal->format('H:i'),
            'duration_minutes' => (int) $request->duration_minutes,
            'duration_label' => $this->durationLabel((int) $request->duration_minutes),
            'pickup_address' => $request->pickup_address,
            'decline_reason' => $request->decline_reason,
            'created_at' => $request->created_at,
        ];

        if ($request->suggested_starts_at !== null) {
            $suggestedLocal = OrganisationTime::utcToLocal($request->suggested_starts_at, $org);
            $suggestedEnd = $suggestedLocal->modify('+' . (int) $request->duration_minutes . ' minutes');
            $payload['suggested'] = [
                'starts_at_local' => OrganisationTime::formatLocalIso($suggestedLocal),
                'starts_at_day' => $suggestedLocal->format('D j M'),
                'starts_at_time' => $suggestedLocal->format('H:i'),
                'ends_at_time' => $suggestedEnd->format('H:i'),
            ];
        }

        if ($includeLearner && $request->learner !== null) {
            $payload['learner_id'] = (int) $request->learner_id;
            $payload['learner_name'] = $request->learner->fullName;
            $payload['learner_first_name'] = $this->firstName($request->learner->fullName);
        }

        return $payload;
    }

    private function durationLabel(int $minutes): string
    {
        if ($minutes % 60 === 0) {
            $h = (int) ($minutes / 60);

            return $h === 1 ? '1 hour' : $h . ' hours';
        }

        return $minutes . ' min';
    }

    private function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        return $parts[0] !== '' ? $parts[0] : $fullName;
    }

    private function findByMutation(int $orgId, string $mutationId): ?LessonBookingRequest
    {
        /** @var LessonBookingRequest|null $request */
        $request = LessonBookingRequest::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'client_mutation_id' => $mutationId,
            ])
            ->one();

        return $request;
    }

    private function findOwned(int $id): LessonBookingRequest
    {
        /** @var LessonBookingRequest|null $request */
        $request = TenantContext::scopeByOrganisation(LessonBookingRequest::find())
            ->andWhere(['id' => $id])
            ->with(['learner'])
            ->one();
        if ($request === null) {
            throw new NotFoundHttpException('Request not found.');
        }

        return $request;
    }

    private function findLearnerOwned(int $learnerId): Learner
    {
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }

    private function findInstructorOwned(int $instructorId): Instructor
    {
        /** @var Instructor|null $instructor */
        $instructor = TenantContext::scopeByOrganisation(Instructor::find())
            ->andWhere(['id' => $instructorId])
            ->one();
        if ($instructor === null) {
            throw new NotFoundHttpException('Instructor not found.');
        }

        return $instructor;
    }

    /**
     * @return array{0: Learner, 1: Organisation}
     */
    private function requirePortalLearnerOrg(): array
    {
        [$learner, $org] = array_slice($this->requirePortalContext(), 0, 2);

        return [$learner, $org];
    }

    /**
     * @return array{0: Learner, 1: Organisation, 2: Instructor|null}
     */
    private function requirePortalContext(): array
    {
        $account = PortalContext::requireAccount();
        /** @var Learner|null $learner */
        $learner = Learner::findOne([
            'id' => (int) $account->learner_id,
            'organisation_id' => (int) $account->organisation_id,
        ]);
        if ($learner === null || $learner->archived_at !== null) {
            throw new NotFoundHttpException('Your learner record is unavailable.');
        }
        /** @var Organisation|null $org */
        $org = Organisation::findOne(['id' => (int) $account->organisation_id]);
        if ($org === null) {
            throw new NotFoundHttpException('Business not found.');
        }
        /** @var Instructor|null $instructor */
        $instructor = Instructor::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        return [$learner, $org, $instructor];
    }

    private function requireOrganisation(): Organisation
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $org;
    }

    private function normalizeMutationId(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = trim((string) $raw);
        if ($id === '' || strlen($id) > 64) {
            return null;
        }

        return $id;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors !== [] ? (string) reset($errors) : 'Could not save.';
    }
}
