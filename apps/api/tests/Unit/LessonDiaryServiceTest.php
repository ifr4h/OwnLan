<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class LessonDiaryServiceTest extends Unit
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
            'name' => 'Diary Instructor',
            'email' => 'diary@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
    }

    public function testDayViewReturnsOnlyThatDay(): void
    {
        $a = $this->learners->create([
            'first_name' => 'Amy',
            'last_name' => 'Day',
            'mobile' => '07700903001',
        ]);
        $b = $this->learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Next',
            'mobile' => '07700903002',
        ]);

        $this->lessons->create([
            'learner_id' => $a['id'],
            'starts_at_local' => '2026-09-15 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->create([
            'learner_id' => $b['id'],
            'starts_at_local' => '2026-09-16 10:00',
            'duration_minutes' => 60,
        ]);

        $diary = $this->lessons->diary('day', '2026-09-15', $this->fixedNow('2026-09-15 09:00'));

        $this->assertSame('day', $diary['view']);
        $this->assertSame('2026-09-15', $diary['date']);
        $this->assertCount(1, $diary['days']);
        $this->assertCount(1, $diary['lessons']);
        $this->assertSame('Amy Day', $diary['lessons'][0]['learner_name']);
        $this->assertTrue($diary['is_today']);
    }

    public function testWeekViewMondayToSunday(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Wes',
            'last_name' => 'Kly',
            'mobile' => '07700903003',
        ]);
        // Wednesday 16 Sep 2026 is mid-week; week should be Mon 14 – Sun 20.
        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-14 09:00',
        ]);
        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-20 15:00',
        ]);
        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-21 10:00', // next Monday — out
        ]);

        $diary = $this->lessons->diary('week', '2026-09-16', $this->fixedNow('2026-09-16 12:00'));

        $this->assertSame('week', $diary['view']);
        $this->assertSame('2026-09-14', $diary['range_start']);
        $this->assertSame('2026-09-20', $diary['range_end']);
        $this->assertCount(7, $diary['days']);
        $this->assertCount(2, $diary['lessons']);
    }

    public function testOverlapAwareness(): void
    {
        $a = $this->learners->create([
            'first_name' => 'One',
            'last_name' => 'Lap',
            'mobile' => '07700903004',
        ]);
        $b = $this->learners->create([
            'first_name' => 'Two',
            'last_name' => 'Lap',
            'mobile' => '07700903005',
        ]);

        $this->lessons->create([
            'learner_id' => $a['id'],
            'starts_at_local' => '2026-09-15 10:00',
            'duration_minutes' => 90,
        ]);
        $this->lessons->create([
            'learner_id' => $b['id'],
            'starts_at_local' => '2026-09-15 11:00',
            'duration_minutes' => 60,
        ]);

        $diary = $this->lessons->diary('day', '2026-09-15', $this->fixedNow('2026-09-15 08:00'));

        $this->assertSame(2, $diary['overlap_count']);
        $this->assertTrue($diary['lessons'][0]['overlaps']);
        $this->assertTrue($diary['lessons'][1]['overlaps']);
    }

    private function fixedNow(string $local): \DateTimeImmutable
    {
        return OrganisationTime::localToUtc($local, $this->org);
    }
}
