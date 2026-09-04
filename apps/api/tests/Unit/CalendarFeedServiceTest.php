<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\IcsCalendarBuilder;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\Organisation;
use app\services\AuthService;
use app\services\CalendarFeedService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;
use Yii;

class CalendarFeedServiceTest extends Unit
{
    protected UnitTester $tester;

    private CalendarFeedService $calendar;
    private LessonService $lessons;
    private LearnerService $learners;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, '
            . 'lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users '
            . 'RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->calendar = new CalendarFeedService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Cal Instructor',
            'email' => 'cal@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->org->timezone = 'Europe/London';
        $this->org->save(false, ['timezone']);
    }

    public function testTokenGenerationAndRevocation(): void
    {
        $settings = $this->calendar->connect($this->org);
        $this->org->refresh();
        $this->assertTrue($settings['connected']);
        $this->assertNotEmpty($settings['subscription_url']);
        $this->assertGreaterThanOrEqual(32, strlen((string) $this->org->calendar_feed_token));

        $old = (string) $this->org->calendar_feed_token;
        $regenerated = $this->calendar->regenerate($this->org);
        $this->org->refresh();
        $this->assertNotSame($old, $this->org->calendar_feed_token);

        $ics = $this->calendar->feedByToken((string) $this->org->calendar_feed_token);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);

        $this->expectException(\yii\web\NotFoundHttpException::class);
        $this->calendar->feedByToken($old);

        $this->calendar->revoke($this->org);
        $this->org->refresh();
        $this->assertNull($this->org->calendar_feed_token);
    }

    public function testScheduledLessonIncludedWithStableUid(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700906001',
            'default_pickup_address' => '12 High Street, Kingston',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-10 10:15',
            'duration_minutes' => 90,
        ]);

        $this->calendar->connect($this->org);
        $this->org->refresh();
        $ics = $this->calendar->feedByToken((string) $this->org->calendar_feed_token);

        $uid = IcsCalendarBuilder::lessonUid((int) $lesson['id']);
        $this->assertStringContainsString('UID:' . $uid, $ics);
        $this->assertStringContainsString('DTSTART;TZID=Europe/London:20260910T101500', $ics);
        $this->assertStringContainsString('DTEND;TZID=Europe/London:20260910T114500', $ics);
    }

    public function testPrivatePrivacyModeHidesPupilName(): void
    {
        $this->org->calendar_privacy_mode = CalendarFeedService::PRIVACY_PRIVATE;
        $this->org->save(false, ['calendar_privacy_mode']);

        $pupil = $this->learners->create([
            'first_name' => 'Private',
            'last_name' => 'Pupil',
            'mobile' => '07700906002',
        ]);
        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-11 09:00',
            'duration_minutes' => 60,
        ]);

        $this->calendar->connect($this->org);
        $this->org->refresh();
        $ics = $this->calendar->feedByToken((string) $this->org->calendar_feed_token);

        $this->assertStringContainsString('SUMMARY:Driving lesson', $ics);
        $this->assertStringNotContainsString('Private Pupil', $ics);
    }

    public function testCancelledFutureLessonMarkedCancelled(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Cancel',
            'last_name' => 'Case',
            'mobile' => '07700906003',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-12-01 14:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->cancel((int) $lesson['id'], ['reason' => 'Ill']);

        $this->calendar->connect($this->org);
        $this->org->refresh();
        $ics = $this->calendar->feedByToken((string) $this->org->calendar_feed_token);

        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
    }

    public function testBstTransitionFormatting(): void
    {
        $tz = OrganisationTime::timezoneFor($this->org);
        $summer = new DateTimeImmutable('2026-07-15 10:00:00', $tz);
        $winter = new DateTimeImmutable('2026-01-15 10:00:00', $tz);

        $summerUtc = $summer->setTimezone(new DateTimeZone('UTC'))->format('H:i');
        $winterUtc = $winter->setTimezone(new DateTimeZone('UTC'))->format('H:i');

        $this->assertSame('09:00', $summerUtc);
        $this->assertSame('10:00', $winterUtc);
    }
}
