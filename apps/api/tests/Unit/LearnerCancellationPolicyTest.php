<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Lesson;
use app\models\LessonCharge;
use app\models\Organisation;
use app\services\AuthService;
use app\services\CancellationPolicyService;
use app\services\LearnerService;
use app\services\LessonBookingRequestService;
use app\services\LessonService;
use app\services\PortalAuthService;
use app\services\SettingsService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;

class LearnerCancellationPolicyTest extends Unit
{
    protected UnitTester $tester;

    private SettingsService $settings;
    private LearnerService $learners;
    private LessonService $lessons;
    private PortalAuthService $portalAuth;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, learner_portal_accounts, lesson_booking_requests, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        TenantContext::clear();
        $this->settings = new SettingsService();
        $this->learners = new LearnerService();
        $this->lessons = new LessonService();
        $this->portalAuth = new PortalAuthService();
    }

    public function testEnoughNoticeWaivesCharge(): void
    {
        [$email, , $learnerId] = $this->seedReadyToCancel();
        $starts = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+5 days')->setTime(14, 0);
        $lessonId = $this->createLessonAsInstructor($learnerId, $starts);
        $this->loginPortal($email);

        $cancelled = (new LessonBookingRequestService())->cancelLessonFromPortal($lessonId);
        $this->assertSame(Lesson::STATUS_CANCELLED, $cancelled['status']);
        $this->assertSame(Lesson::CANCELLED_BY_LEARNER, Lesson::findOne($lessonId)?->cancelled_by);
        $this->assertSame('waived', Lesson::findOne($lessonId)?->settlement);
        $this->assertSame(0, (int) LessonCharge::find()->count());
        $notice = Lesson::findOne($lessonId)?->cancellation_notice_hours;
        $this->assertNotNull($notice);
        $this->assertGreaterThanOrEqual(100, (int) $notice);
    }

    public function testShortNoticeDecideLeavesUnsettled(): void
    {
        [$email, , $learnerId] = $this->seedReadyToCancel([
            'cancellation_notice_hours' => 48,
            'cancellation_late_policy' => Organisation::CANCELLATION_LATE_DECIDE,
        ]);
        $starts = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+6 hours');
        $lessonId = $this->createLessonAsInstructor($learnerId, $starts);
        $this->loginPortal($email);

        $cancelled = (new LessonBookingRequestService())->cancelLessonFromPortal($lessonId, [
            'reason' => 'Feeling unwell',
        ]);
        $lesson = Lesson::findOne($lessonId);
        $this->assertSame(Lesson::STATUS_CANCELLED, $cancelled['status']);
        $this->assertNull($lesson?->settlement);
        $this->assertSame('Feeling unwell', $lesson?->cancellation_reason);
        $this->assertSame(6, (int) $lesson?->cancellation_notice_hours);

        Yii::$app->portalUser->logout();
        $this->loginInstructor();
        $this->assertTrue((bool) ($this->lessons->get($lessonId)['needs_cancellation_settlement'] ?? false));
        $row = $this->lessons->get($lessonId);
        $this->assertSame(6, (int) ($row['cancellation_notice_hours'] ?? 0));
        $this->assertNotEmpty($row['cancellation_notice_label'] ?? null);
    }

    public function testShortNoticeChargeCreatesOutstanding(): void
    {
        [$email, , $learnerId] = $this->seedReadyToCancel([
            'cancellation_notice_hours' => 48,
            'cancellation_late_policy' => Organisation::CANCELLATION_LATE_CHARGE,
        ]);
        $starts = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+6 hours');
        $lessonId = $this->createLessonAsInstructor($learnerId, $starts);
        $this->loginPortal($email);

        $cancelled = (new LessonBookingRequestService())->cancelLessonFromPortal($lessonId, [
            'reason' => 'Work ran late',
        ]);
        $this->assertSame('outstanding', $cancelled['settlement'] ?? Lesson::findOne($lessonId)?->settlement);
        $this->assertSame(1, (int) LessonCharge::find()->count());
    }

    public function testShortNoticeRequiresReason(): void
    {
        [$email, , $learnerId] = $this->seedReadyToCancel([
            'cancellation_notice_hours' => 48,
        ]);
        $starts = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+6 hours');
        $lessonId = $this->createLessonAsInstructor($learnerId, $starts);
        $this->loginPortal($email);

        $this->expectException(BadRequestHttpException::class);
        (new LessonBookingRequestService())->cancelLessonFromPortal($lessonId);
    }

    public function testInstructorCanSettlePendingCancellation(): void
    {
        [$email, , $learnerId] = $this->seedReadyToCancel([
            'cancellation_late_policy' => Organisation::CANCELLATION_LATE_DECIDE,
        ]);
        $starts = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+6 hours');
        $lessonId = $this->createLessonAsInstructor($learnerId, $starts);
        $this->loginPortal($email);
        (new LessonBookingRequestService())->cancelLessonFromPortal($lessonId, [
            'reason' => 'Family emergency',
        ]);

        Yii::$app->portalUser->logout();
        $this->loginInstructor();

        $settled = $this->lessons->settleCancellation($lessonId, ['charge' => 'waived']);
        $this->assertSame('waived', $settled['settlement']);
        $this->assertFalse((bool) ($settled['needs_cancellation_settlement'] ?? true));
    }

    public function testPolicyPreviewMessages(): void
    {
        $this->seedReadyToCancel([
            'cancellation_notice_hours' => 24,
            'cancellation_late_policy' => Organisation::CANCELLATION_LATE_CHARGE,
        ]);
        $this->loginInstructor();
        $org = Organisation::findOne(TenantContext::requireOrganisationId());
        $this->assertNotNull($org);

        $learner = $this->learners->create([
            'first_name' => 'Preview',
            'last_name' => 'Pupil',
            'mobile' => '07700900999',
        ]);
        $starts = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+2 hours');
        $local = $starts->setTimezone(new DateTimeZone('Europe/London'))->format('Y-m-d H:i');
        $lesson = $this->lessons->create([
            'learner_id' => $learner['id'],
            'starts_at_local' => $local,
            'duration_minutes' => 60,
        ]);
        /** @var Lesson $model */
        $model = Lesson::findOne((int) $lesson['id']);
        $preview = (new CancellationPolicyService())->previewForLearner($model, $org);
        $this->assertFalse($preview['sufficient_notice']);
        $this->assertSame(CancellationPolicyService::OUTCOME_WILL_CHARGE, $preview['outcome']);
        $this->assertTrue($preview['reason_required']);
        $this->assertStringContainsString('still be charged', $preview['message']);
    }

    public function testInstructorCanRecordNoticeWhenCancelling(): void
    {
        [, , $learnerId] = $this->seedReadyToCancel();
        $starts = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+2 days')->setTime(10, 0);
        $lessonId = $this->createLessonAsInstructor($learnerId, $starts);
        $this->loginInstructor();

        $cancelled = $this->lessons->cancel($lessonId, [
            'cancellation_notice_hours' => 6,
        ]);
        $this->assertSame(Lesson::STATUS_CANCELLED, $cancelled['status']);
        $this->assertSame(Lesson::CANCELLED_BY_LEARNER, $cancelled['cancelled_by']);
        $this->assertSame(6, (int) $cancelled['cancellation_notice_hours']);
        $this->assertSame('6 hours’ notice', $cancelled['cancellation_notice_label']);
        $this->assertSame(6, (int) Lesson::findOne($lessonId)?->cancellation_notice_hours);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{0: string, 1: int, 2: int}
     */
    private function seedReadyToCancel(array $settings = []): array
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'cancel-policy@example.com',
            'password' => 'password123',
        ]);
        $orgId = TenantContext::requireOrganisationId();
        $this->settings->update(array_merge([
            'booking_mode' => 'instant',
            'booking_minimum_notice_hours' => 0,
            'booking_advance_weeks' => 8,
            'learner_can_cancel' => true,
            'cancellation_notice_hours' => 48,
            'cancellation_late_policy' => Organisation::CANCELLATION_LATE_DECIDE,
        ], $settings));

        $learner = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700900001',
            'email' => 'sarah.cancel@example.com',
            'default_pickup_address' => '12 High Street',
        ]);
        $email = $this->activatePortal((int) $learner['id']);

        return [$email, $orgId, (int) $learner['id']];
    }

    private function createLessonAsInstructor(int $learnerId, DateTimeImmutable $startsUtc): int
    {
        $this->loginInstructor();
        $local = $startsUtc->setTimezone(new DateTimeZone('Europe/London'))->format('Y-m-d H:i');
        $lesson = $this->lessons->create([
            'learner_id' => $learnerId,
            'starts_at_local' => $local,
            'duration_minutes' => 60,
            'pickup_address' => '12 High Street',
        ]);
        Yii::$app->user->logout();
        TenantContext::clear();

        return (int) $lesson['id'];
    }

    private function loginPortal(string $email): void
    {
        $this->portalAuth->login([
            'email' => $email,
            'password' => 'portalpass123',
        ]);
    }

    private function loginInstructor(): void
    {
        (new AuthService())->login([
            'email' => 'cancel-policy@example.com',
            'password' => 'password123',
        ]);
    }

    private function activatePortal(int $learnerId): string
    {
        $this->loginInstructor();
        $invite = $this->portalAuth->inviteForLearner($learnerId);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $token = (string) ($query['token'] ?? '');
        $this->portalAuth->activate([
            'token' => $token,
            'password' => 'portalpass123',
        ]);
        Yii::$app->portalUser->logout();

        /** @var \app\models\LearnerPortalAccount|null $account */
        $account = \app\models\LearnerPortalAccount::findOne(['learner_id' => $learnerId]);

        return (string) ($account?->email ?? '');
    }
}
