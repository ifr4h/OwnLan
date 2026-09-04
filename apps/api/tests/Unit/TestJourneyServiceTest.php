<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Learner;
use app\models\Organisation;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonService;
use app\services\TestJourneyService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class TestJourneyServiceTest extends Unit
{
    protected UnitTester $tester;

    private TestJourneyService $journey;
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
        $this->journey = new TestJourneyService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Test Journey Instructor',
            'email' => 'testjourney@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
    }

    public function testNoTestDateReturnsNull(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'No',
            'last_name' => 'Test',
            'mobile' => '07700902001',
        ]);
        $learner = Learner::findOne(['id' => $pupil['id']]);

        $this->assertNull($this->journey->build($learner, $this->org, $this->fixedNow('2026-09-15 12:00')));
        $this->assertArrayHasKey('test_journey', $pupil);
        $this->assertNull($pupil['test_journey']);
    }

    public function testCountdownAndLessonsBeforeTest(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Alex',
            'last_name' => 'Testbound',
            'mobile' => '07700902002',
        ]);
        $this->learners->update((int) $pupil['id'], [
            'test_date' => '2026-10-03',
            'test_centre' => 'Leeds',
        ]);
        $id = (int) $pupil['id'];

        $this->lessons->create(['learner_id' => $id, 'starts_at_local' => '2026-09-20 10:00']);
        $this->lessons->create(['learner_id' => $id, 'starts_at_local' => '2026-09-27 10:00']);
        // After test date — must not count
        $this->lessons->create(['learner_id' => $id, 'starts_at_local' => '2026-10-10 10:00']);

        $completed = $this->lessons->create(['learner_id' => $id, 'starts_at_local' => '2026-09-01 10:00']);
        $this->lessons->complete((int) $completed['id'], [
            'client_mutation_id' => 'tj-1',
            'learner_summary' => 'Mirrors improving',
            'next_focus' => 'Independent driving',
        ]);

        $learner = Learner::findOne(['id' => $id]);
        $journey = $this->journey->build($learner, $this->org, $this->fixedNow('2026-09-15 12:00'));

        $this->assertNotNull($journey);
        $this->assertSame(18, $journey['days_until']);
        $this->assertSame('Test in 18 days', $journey['countdown_label']);
        $this->assertSame('Leeds', $journey['test_centre']);
        $this->assertSame(2, $journey['lessons_booked_before_test']);
        $this->assertSame('Mirrors improving', $journey['progress_summary']);
        $this->assertSame('Independent driving', $journey['next_focus']);
        $this->assertTrue($journey['show_on_today']);
    }

    public function testFarFutureNotShownOnTodayCompact(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Far',
            'last_name' => 'Away',
            'mobile' => '07700902003',
        ]);
        $this->learners->update((int) $pupil['id'], [
            'test_date' => '2027-03-01',
        ]);
        $learner = Learner::findOne(['id' => $pupil['id']]);

        $full = $this->journey->build($learner, $this->org, $this->fixedNow('2026-09-15 12:00'));
        $compact = $this->journey->buildCompact($learner, $this->org, $this->fixedNow('2026-09-15 12:00'));

        $this->assertNotNull($full);
        $this->assertFalse($full['show_on_today']);
        $this->assertNull($compact);
    }

    public function testDoesNotDeclareReadiness(): void
    {
        $pupil = $this->learners->update(
            (int) $this->learners->create([
                'first_name' => 'Calm',
                'last_name' => 'Context',
                'mobile' => '07700902004',
            ])['id'],
            ['test_date' => '2026-09-20'],
        );

        $json = json_encode($pupil['test_journey']);
        $this->assertStringNotContainsStringIgnoringCase('pass', (string) $json);
        $this->assertStringNotContainsStringIgnoringCase('ready', (string) $json);
        $this->assertStringNotContainsStringIgnoringCase('score', (string) $json);
    }

    private function fixedNow(string $local): \DateTimeImmutable
    {
        return OrganisationTime::localToUtc($local, $this->org);
    }
}
