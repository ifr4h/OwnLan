<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\PortalContext;
use app\components\TenantContext;
use app\models\LearnerPortalAccount;
use app\models\Lesson;
use app\models\LessonBookingRequest;
use app\models\Organisation;
use app\services\AuthService;
use app\services\LearnerBookingAvailabilityService;
use app\services\LearnerService;
use app\services\LessonBookingRequestService;
use app\services\LessonService;
use app\services\PortalAuthService;
use app\services\SettingsService;
use app\tests\Support\FakeTravelProvider;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;
use Yii;

class LearnerBookingServiceTest extends Unit
{
    protected UnitTester $tester;

    private LearnerService $learners;
    private LessonService $lessons;
    private SettingsService $settings;
    private PortalAuthService $portalAuth;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lesson_booking_requests, lessons, lesson_series, learner_availability, learner_portal_accounts, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        TenantContext::clear();
        $this->learners = new LearnerService();
        $this->lessons = new LessonService();
        $this->settings = new SettingsService();
        $this->portalAuth = new PortalAuthService();
    }

    public function testManualModeBlocksLearnerAvailability(): void
    {
        [$email] = $this->seedInstructorAndLearner();
        $this->loginPortal($email, 'portalpass123');

        $this->expectException(\yii\web\ForbiddenHttpException::class);
        (new LearnerBookingAvailabilityService(new FakeTravelProvider()))->forPortalLearner();
    }

    public function testRequestModeCreatesPendingRequestNotLesson(): void
    {
        [$email] = $this->seedInstructorAndLearner();
        $this->enableBookingMode('request');
        $this->loginPortal($email, 'portalpass123');

        $result = (new LessonBookingRequestService())->createFromPortal([
            'starts_at_local' => '2026-09-17 14:00',
            'duration_minutes' => 60,
            'pickup_address' => '12 High Street',
        ]);

        $this->assertSame('requested', $result['outcome']);
        $this->assertSame(LessonBookingRequest::STATUS_PENDING, $result['request']['status']);
        $lessonCount = (int) Lesson::find()->count();
        $this->assertSame(0, $lessonCount);
    }

    public function testInstantModeCreatesLesson(): void
    {
        [$email] = $this->seedInstructorAndLearner();
        $this->enableBookingMode('instant');
        $this->loginPortal($email, 'portalpass123');

        $result = (new LessonBookingRequestService())->createFromPortal([
            'starts_at_local' => '2026-09-17 14:00',
            'duration_minutes' => 60,
            'pickup_address' => '12 High Street',
        ]);

        $this->assertSame('booked', $result['outcome']);
        $this->assertSame(1, (int) Lesson::find()->count());
        $this->assertSame(Lesson::STATUS_SCHEDULED, Lesson::find()->one()->status);
    }

    public function testAvailabilityRespectsWorkingHoursAndLessons(): void
    {
        [$email, , $learnerId] = $this->seedInstructorAndLearner();
        $this->enableBookingMode('request');

        Yii::$app->portalUser->logout();
        $this->loginInstructor();
        $this->lessons->create([
            'learner_id' => $learnerId,
            'starts_at_local' => '2026-09-17 10:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Anchor LS1 1AA',
        ]);

        $this->loginPortal($email, 'portalpass123');

        $payload = (new LearnerBookingAvailabilityService(new FakeTravelProvider()))->forPortalLearner();
        $timesOnDay = [];
        foreach ($payload['days'] ?? [] as $week) {
            foreach ($week['days'] ?? [] as $day) {
                if (($day['date'] ?? '') !== '2026-09-17') {
                    continue;
                }
                foreach ($day['times'] ?? [] as $slot) {
                    $timesOnDay[] = (string) $slot['starts_at_time'];
                }
            }
        }
        $this->assertNotContains('10:00', $timesOnDay);
        $this->assertNotContains('08:00', $timesOnDay);
    }

    public function testAcceptanceRevalidatesSlot(): void
    {
        [$email, , $learnerId] = $this->seedInstructorAndLearner();
        $this->enableBookingMode('request');
        $this->loginPortal($email, 'portalpass123');

        $created = (new LessonBookingRequestService())->createFromPortal([
            'starts_at_local' => '2026-09-17 14:00',
            'duration_minutes' => 60,
        ]);
        $requestId = (int) $created['request']['id'];

        Yii::$app->portalUser->logout();
        $this->loginInstructor();

        $this->lessons->create([
            'learner_id' => $learnerId,
            'starts_at_local' => '2026-09-17 14:00',
            'duration_minutes' => 60,
        ]);

        $this->expectException(\yii\web\BadRequestHttpException::class);
        (new LessonBookingRequestService())->accept($requestId);
    }

    public function testDoubleBookRaceProtection(): void
    {
        [$emailA] = $this->seedInstructorAndLearner();
        $this->enableBookingMode('instant');

        $learnerB = $this->learners->create([
            'first_name' => 'Yusuf',
            'last_name' => 'Khan',
            'mobile' => '07700900099',
            'email' => 'yusuf@example.com',
            'default_pickup_address' => 'Other Road',
        ]);
        $emailB = $this->activatePortal((int) $learnerB['id']);

        $this->loginPortal($emailA, 'portalpass123');
        $service = new LessonBookingRequestService();
        $service->createFromPortal([
            'starts_at_local' => '2026-09-17 14:00',
            'duration_minutes' => 60,
        ]);

        Yii::$app->portalUser->logout();
        $this->loginPortal($emailB, 'portalpass123');

        $this->expectException(\yii\web\ConflictHttpException::class);
        $service->createFromPortal([
            'starts_at_local' => '2026-09-17 14:00',
            'duration_minutes' => 60,
        ]);
    }

    public function testCancellationFeedsEmptySeat(): void
    {
        [$email] = $this->seedInstructorAndLearner();
        $this->enableBookingMode('instant');
        $this->loginPortal($email, 'portalpass123');

        $booked = (new LessonBookingRequestService())->createFromPortal([
            'starts_at_local' => '2026-09-17 14:00',
            'duration_minutes' => 60,
            'pickup_address' => '12 High Street',
        ]);
        $lessonId = (int) $booked['lesson']['id'];

        $cancelled = (new LessonBookingRequestService())->cancelLessonFromPortal($lessonId);
        $this->assertSame(Lesson::STATUS_CANCELLED, $cancelled['status']);
        $this->assertNotNull(Lesson::findOne(['id' => $lessonId, 'status' => Lesson::STATUS_CANCELLED]));
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function seedInstructorAndLearner(): array
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'booking@example.com',
            'password' => 'password123',
        ]);
        $orgId = TenantContext::requireOrganisationId();
        $learner = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700900001',
            'email' => 'sarah@example.com',
            'default_pickup_address' => '12 High Street',
        ]);
        $email = $this->activatePortal((int) $learner['id']);

        return [$email, $orgId, (int) $learner['id']];
    }

    private function loginPortal(string $email, string $password): void
    {
        $this->portalAuth->login([
            'email' => $email,
            'password' => $password,
        ]);
    }

    private function loginInstructor(): void
    {
        (new AuthService())->login([
            'email' => 'booking@example.com',
            'password' => 'password123',
        ]);
    }

    private function enableBookingMode(string $mode): void
    {
        $this->loginInstructor();
        $this->settings->update([
            'booking_mode' => $mode,
            'booking_minimum_notice_hours' => 0,
            'booking_advance_weeks' => 8,
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

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function flattenSlotTimes(array $payload): array
    {
        $times = [];
        foreach ($payload['days'] ?? [] as $week) {
            foreach ($week['days'] ?? [] as $day) {
                foreach ($day['times'] ?? [] as $slot) {
                    $times[] = (string) $slot['starts_at_time'];
                }
            }
        }

        return $times;
    }
}
