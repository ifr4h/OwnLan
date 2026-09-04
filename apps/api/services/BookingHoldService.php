<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\PortalContext;
use app\components\TenantContext;
use app\models\BookingHold;
use app\models\Instructor;
use app\models\Learner;
use app\models\Lesson;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\NotFoundHttpException;

/**
 * Short-lived slot holds for pay-before-book flows.
 */
class BookingHoldService
{
    public const HOLD_MINUTES = 10;

    private LearnerBookingAvailabilityService $availability;

    public function __construct(
        ?LearnerBookingAvailabilityService $availability = null,
    ) {
        $this->availability = $availability ?? new LearnerBookingAvailabilityService();
    }

    /**
     * @return array<string, mixed>
     */
    public function createForPortal(array $data): array
    {
        $learnerId = PortalContext::requireLearnerId();
        $orgId = PortalContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        $learner = Learner::findOne(['id' => $learnerId, 'organisation_id' => $orgId]);
        $instructor = Instructor::find()->andWhere(['organisation_id' => $orgId])->orderBy(['id' => SORT_ASC])->one();
        if ($org === null || $learner === null || $instructor === null) {
            throw new NotFoundHttpException('Booking not available.');
        }

        if (($org->booking_payment_policy ?? 'none') !== 'require_to_confirm') {
            throw new BadRequestHttpException('Payment is not required to book.');
        }
        if ($org->booking_mode() !== Organisation::BOOKING_MODE_INSTANT) {
            throw new BadRequestHttpException('Pay and book is only available for instant booking.');
        }

        $startsLocal = trim((string) ($data['starts_at_local'] ?? ''));
        if ($startsLocal === '') {
            throw new BadRequestHttpException('Choose a lesson time.');
        }
        $startUtc = OrganisationTime::localToUtc($startsLocal, $org);
        $duration = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : $org->defaultLessonDurationMinutes();
        $pickup = trim((string) ($data['pickup_address'] ?? '')) ?: $learner->default_pickup_address;
        $pricePence = $this->lessonPricePence($learner, $org, $duration);

        $this->expireStaleHolds($org);
        $this->assertNoActiveHoldConflict($org, (int) $instructor->id, $startUtc, $duration);

        $this->availability->assertSlotAvailable(
            $org,
            $instructor,
            $learner,
            $startUtc,
            $duration,
            $pickup,
        );

        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + self::HOLD_MINUTES * 60);
        $hold = new BookingHold();
        $hold->organisation_id = (int) $org->id;
        $hold->instructor_id = (int) $instructor->id;
        $hold->learner_id = (int) $learner->id;
        $hold->starts_at = OrganisationTime::formatUtc($startUtc);
        $hold->duration_minutes = $duration;
        $hold->price_pence = $pricePence;
        $hold->pickup_address = $pickup;
        $hold->status = BookingHold::STATUS_ACTIVE;
        $hold->expires_at = $expires;
        $hold->created_at = $now;
        $hold->updated_at = $now;
        if (!$hold->save()) {
            throw new BadRequestHttpException('Could not hold this time.');
        }

        return [
            'hold_id' => (int) $hold->id,
            'starts_at_local' => $startsLocal,
            'duration_minutes' => $duration,
            'price_pence' => $pricePence,
            'price_label' => Money::formatPence($pricePence),
            'expires_at' => $expires,
            'expires_in_seconds' => self::HOLD_MINUTES * 60,
        ];
    }

    public function convertToLesson(int $holdId, int $paymentId): Lesson
    {
        $hold = BookingHold::findOne(['id' => $holdId]);
        if ($hold === null) {
            throw new NotFoundHttpException('Hold not found.');
        }
        if ($hold->status === BookingHold::STATUS_CONVERTED && $hold->lesson_id) {
            $lesson = Lesson::findOne(['id' => (int) $hold->lesson_id]);

            return $lesson ?? throw new NotFoundHttpException('Lesson not found.');
        }

        $org = Organisation::findOne(['id' => (int) $hold->organisation_id]);
        $learner = Learner::findOne(['id' => (int) $hold->learner_id]);
        $instructor = Instructor::findOne(['id' => (int) $hold->instructor_id]);
        if ($org === null || $learner === null || $instructor === null) {
            throw new NotFoundHttpException('Hold not found.');
        }

        $startUtc = new DateTimeImmutable((string) $hold->starts_at, new DateTimeZone('UTC'));
        $tx = Yii::$app->db->beginTransaction();
        try {
            $hold->refresh();
            if ($hold->status === BookingHold::STATUS_CONVERTED && $hold->lesson_id) {
                $tx->commit();
                $lesson = Lesson::findOne(['id' => (int) $hold->lesson_id]);

                return $lesson ?? throw new NotFoundHttpException('Lesson not found.');
            }

            if ($hold->status !== BookingHold::STATUS_ACTIVE && $hold->expires_at < gmdate('Y-m-d H:i:s')) {
                throw new ConflictHttpException('That hold has expired.');
            }

            $this->availability->assertSlotAvailable(
                $org,
                $instructor,
                $learner,
                $startUtc,
                (int) $hold->duration_minutes,
                $hold->pickup_address,
            );

            $lesson = new Lesson();
            $lesson->organisation_id = (int) $org->id;
            $lesson->instructor_id = (int) $instructor->id;
            $lesson->learner_id = (int) $learner->id;
            $lesson->status = Lesson::STATUS_SCHEDULED;
            $lesson->duration_minutes = (int) $hold->duration_minutes;
            $lesson->starts_at = OrganisationTime::formatUtc($startUtc);
            $lesson->pickup_address = $hold->pickup_address ?: $learner->default_pickup_address;
            $lesson->price_pence = (int) $hold->price_pence;
            $lesson->created_at = gmdate('Y-m-d H:i:s');
            $lesson->updated_at = gmdate('Y-m-d H:i:s');
            if (!$lesson->save()) {
                throw new BadRequestHttpException('Could not create lesson.');
            }
            $lessonId = (int) $lesson->id;
            $hold->lesson_id = $lessonId;
            $hold->status = BookingHold::STATUS_CONVERTED;
            $hold->updated_at = gmdate('Y-m-d H:i:s');
            $hold->save(false);

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $lesson = Lesson::findOne(['id' => $lessonId]);

        return $lesson ?? throw new NotFoundHttpException('Lesson not found.');
    }

    public function expireStaleHolds(?Organisation $org = null): int
    {
        $q = BookingHold::find()
            ->andWhere(['status' => BookingHold::STATUS_ACTIVE])
            ->andWhere(['<', 'expires_at', gmdate('Y-m-d H:i:s')]);
        if ($org !== null) {
            $q->andWhere(['organisation_id' => (int) $org->id]);
        }
        $count = 0;
        foreach ($q->all() as $hold) {
            /** @var BookingHold $hold */
            $hold->status = BookingHold::STATUS_EXPIRED;
            $hold->updated_at = gmdate('Y-m-d H:i:s');
            $hold->save(false, ['status', 'updated_at']);
            $count++;
        }

        return $count;
    }

    private function assertNoActiveHoldConflict(
        Organisation $org,
        int $instructorId,
        DateTimeImmutable $startUtc,
        int $durationMinutes,
    ): void {
        $endUtc = $startUtc->modify('+' . $durationMinutes . ' minutes');
        /** @var BookingHold[] $holds */
        $holds = BookingHold::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'instructor_id' => $instructorId,
                'status' => BookingHold::STATUS_ACTIVE,
            ])
            ->andWhere(['>=', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->all();

        foreach ($holds as $hold) {
            $holdStart = new DateTimeImmutable((string) $hold->starts_at, new DateTimeZone('UTC'));
            $holdEnd = $holdStart->modify('+' . (int) $hold->duration_minutes . ' minutes');
            if ($startUtc < $holdEnd && $endUtc > $holdStart) {
                throw new ConflictHttpException('That time has just been taken.');
            }
        }
    }

    private function lessonPricePence(Learner $learner, Organisation $org, int $durationMinutes): int
    {
        $hourly = (int) ($org->default_hourly_rate_pence ?? 3500);

        return (int) round($hourly * $durationMinutes / 60);
    }
}
