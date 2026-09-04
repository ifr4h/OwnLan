<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\Organisation;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Yii;

class TodayServiceTest extends Unit
{
    protected UnitTester $tester;

    private LessonService $lessons;
    private LearnerService $learners;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Today Instructor',
            'email' => 'today@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
    }

    public function testEmptyDay(): void
    {
        $today = $this->lessons->today($this->fixedNow('2026-09-01 12:00:00'));

        $this->assertSame('2026-09-01', $today['date']);
        $this->assertSame(0, $today['lesson_count']);
        $this->assertNull($today['focus']);
        $this->assertSame([], $today['remaining']);
        $this->assertSame([], $today['lessons']);
    }

    public function testOneUpcomingLessonIsFocus(): void
    {
        $pupil = $this->createPupil('One');
        $this->book($pupil['id'], '2026-09-01 15:00', 60);

        $today = $this->lessons->today($this->fixedNow('2026-09-01 12:00:00'));

        $this->assertSame(1, $today['lesson_count']);
        $this->assertNotNull($today['focus']);
        $this->assertTrue($today['focus']['is_next']);
        $this->assertFalse($today['focus']['is_current']);
        $this->assertSame('One Pupil', $today['focus']['learner_name']);
        $this->assertSame('15:00', $today['focus']['starts_at_time']);
        $this->assertSame('16:00', $today['focus']['ends_at_time']);
        $this->assertSame([], $today['remaining']);
    }

    public function testBusyDayFocusRemainingAndStatuses(): void
    {
        $a = $this->createPupil('Asha');
        $b = $this->createPupil('Ben');
        $c = $this->createPupil('Cara');
        $d = $this->createPupil('Dan');

        $done = $this->book($a['id'], '2026-09-01 08:00', 60);
        $this->lessons->complete((int) $done['id']);

        $cancelled = $this->book($b['id'], '2026-09-01 10:00', 60);
        $this->lessons->cancel((int) $cancelled['id']);

        $this->book($c['id'], '2026-09-01 13:00', 60);
        $this->book($d['id'], '2026-09-01 15:00', 90);

        // Mid current lesson with Cara (13:00–14:00)
        $today = $this->lessons->today($this->fixedNow('2026-09-01 13:30:00'));

        $this->assertSame(3, $today['lesson_count']);
        $this->assertSame('08:00–16:30', $today['summary']['window_label']);
        $this->assertTrue($today['focus']['is_current']);
        $this->assertSame('Cara Pupil', $today['focus']['learner_name']);
        $this->assertCount(1, $today['remaining']);
        $this->assertSame('Dan Pupil', $today['remaining'][0]['learner_name']);
        $this->assertArrayHasKey('needs_you', $today);

        $statuses = array_column($today['lessons'], 'status');
        $this->assertContains(Lesson::STATUS_COMPLETED, $statuses);
        $this->assertContains(Lesson::STATUS_CANCELLED, $statuses);
        $this->assertContains(Lesson::STATUS_SCHEDULED, $statuses);
    }

    public function testCompletedLessonIsNotFocus(): void
    {
        $pupil = $this->createPupil('Done');
        $lesson = $this->book($pupil['id'], '2026-09-01 09:00', 60);
        $this->lessons->complete((int) $lesson['id']);

        $today = $this->lessons->today($this->fixedNow('2026-09-01 12:00:00'));

        $this->assertSame(1, $today['lesson_count']);
        $this->assertNull($today['focus']);
        $this->assertSame(Lesson::STATUS_COMPLETED, $today['lessons'][0]['status']);
    }

    public function testCancelledLessonIsNotFocus(): void
    {
        $pupil = $this->createPupil('Skip');
        $lesson = $this->book($pupil['id'], '2026-09-01 14:00', 60);
        $this->lessons->cancel((int) $lesson['id']);

        $today = $this->lessons->today($this->fixedNow('2026-09-01 12:00:00'));

        $this->assertSame(0, $today['lesson_count']);
        $this->assertNull($today['focus']);
        $this->assertSame(Lesson::STATUS_CANCELLED, $today['lessons'][0]['status']);
        $this->assertSame([], $today['remaining']);
        $this->assertSame('No lessons today', $today['summary']['line']);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPupil(string $first): array
    {
        return $this->learners->create([
            'first_name' => $first,
            'last_name' => 'Pupil',
            'mobile' => '07700' . str_pad((string) random_int(100000, 999999), 6, '0'),
            'default_pickup_address' => $first . ' Road',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function book(int $learnerId, string $localYmdHi, int $duration): array
    {
        return $this->lessons->create([
            'learner_id' => $learnerId,
            'starts_at_local' => $localYmdHi,
            'duration_minutes' => $duration,
        ]);
    }

    private function fixedNow(string $localYmdHis): DateTimeImmutable
    {
        return OrganisationTime::localToUtc($localYmdHis, $this->org);
    }
}
