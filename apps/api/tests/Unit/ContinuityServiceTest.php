<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\ContinuityService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class ContinuityServiceTest extends Unit
{
    protected UnitTester $tester;

    private ContinuityService $continuity;
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
        $this->continuity = new ContinuityService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Continuity Instructor',
            'email' => 'continuity@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
    }

    public function testNewPupilNotSurfaced(): void
    {
        $this->learners->create([
            'first_name' => 'New',
            'last_name' => 'Pupil',
            'mobile' => '07700901001',
        ]);

        $items = $this->continuity->needsAttention($this->fixedNow('2026-09-15 12:00'));
        $this->assertSame([], $items);
    }

    public function testWeeklyPupilOverdueSurfacedWithUsuallyWeekly(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Weekly',
            'mobile' => '07700901002',
        ]);
        $id = (int) $pupil['id'];

        // Weekly Wednesdays — last lesson 12 days before Tue 15 Sep.
        $this->completeLesson($id, '2026-08-13 16:00');
        $this->completeLesson($id, '2026-08-20 16:00');
        $this->completeLesson($id, '2026-08-27 16:00');
        $this->completeLesson($id, '2026-09-03 16:00');

        $items = $this->continuity->needsAttention($this->fixedNow('2026-09-15 12:00'));

        $this->assertCount(1, $items);
        $this->assertSame('Sarah Weekly', $items[0]['learner_name']);
        $this->assertSame('pattern_overdue', $items[0]['kind']);
        $this->assertSame('weekly', $items[0]['usual_cadence']);
        $this->assertContains('No future lesson booked', $items[0]['reasons']);
        $this->assertContains('Usually: weekly', $items[0]['reasons']);
        $this->assertSame(12, $items[0]['days_since_last_lesson']);
    }

    public function testIrregularPupilDoesNotGetUsuallyClaim(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Ira',
            'last_name' => 'Regular',
            'mobile' => '07700901003',
        ]);
        $id = (int) $pupil['id'];

        // Wildly irregular gaps — no clear cadence.
        $this->completeLesson($id, '2026-06-01 10:00');
        $this->completeLesson($id, '2026-06-20 10:00');
        $this->completeLesson($id, '2026-07-02 10:00');
        $this->completeLesson($id, '2026-08-15 10:00');
        $this->completeLesson($id, '2026-09-01 10:00'); // 14 days before 15 Sep → soft surface

        $items = $this->continuity->needsAttention($this->fixedNow('2026-09-15 12:00'));

        $this->assertCount(1, $items);
        $this->assertSame('Ira Regular', $items[0]['learner_name']);
        $this->assertSame('no_future_booking', $items[0]['kind']);
        $this->assertNull($items[0]['usual_cadence']);
        $this->assertContains('No future lesson booked', $items[0]['reasons']);
        $this->assertNotContains('Usually: weekly', $items[0]['reasons']);
    }

    public function testPupilWithFutureBookingExcluded(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Booked',
            'last_name' => 'Ahead',
            'mobile' => '07700901004',
        ]);
        $id = (int) $pupil['id'];

        $this->completeLesson($id, '2026-08-13 16:00');
        $this->completeLesson($id, '2026-08-20 16:00');
        $this->completeLesson($id, '2026-08-27 16:00');
        $this->completeLesson($id, '2026-09-03 16:00');
        $this->lessons->create([
            'learner_id' => $id,
            'starts_at_local' => '2026-09-20 16:00',
        ]);

        $items = $this->continuity->needsAttention($this->fixedNow('2026-09-15 12:00'));
        $this->assertSame([], $items);
    }

    public function testArchivedPupilExcluded(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Arch',
            'last_name' => 'Ived',
            'mobile' => '07700901005',
        ]);
        $id = (int) $pupil['id'];

        $this->completeLesson($id, '2026-08-13 16:00');
        $this->completeLesson($id, '2026-08-20 16:00');
        $this->completeLesson($id, '2026-08-27 16:00');
        $this->completeLesson($id, '2026-09-03 16:00');
        $this->learners->archive($id);

        $items = $this->continuity->needsAttention($this->fixedNow('2026-09-15 12:00'));
        $this->assertSame([], $items);
    }

    public function testInsufficientHistoryNotSurfacedEarly(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Thin',
            'last_name' => 'History',
            'mobile' => '07700901006',
        ]);
        $id = (int) $pupil['id'];

        // Only two completed lessons, 3 days ago — no pattern, under soft gap.
        $this->completeLesson($id, '2026-09-05 10:00');
        $this->completeLesson($id, '2026-09-12 10:00');

        $items = $this->continuity->needsAttention($this->fixedNow('2026-09-15 12:00'));
        $this->assertSame([], $items);
    }

    public function testSoftCaseWithoutPatternAfterQuietGap(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Yusuf',
            'last_name' => 'Quiet',
            'mobile' => '07700901007',
        ]);
        $id = (int) $pupil['id'];

        $this->completeLesson($id, '2026-08-20 11:00');
        $this->completeLesson($id, '2026-09-01 11:00'); // 14 days before 15 Sep

        $items = $this->continuity->needsAttention($this->fixedNow('2026-09-15 12:00'));

        $this->assertCount(1, $items);
        $this->assertSame('Yusuf Quiet', $items[0]['learner_name']);
        $this->assertSame('no_future_booking', $items[0]['kind']);
        $this->assertNull($items[0]['usual_cadence']);
        $this->assertContains('No future lesson booked', $items[0]['reasons']);
    }

    public function testContextForLearnerShowsCadenceAndBookingHorizon(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Weekly',
            'mobile' => '07700901008',
        ]);
        $id = (int) $pupil['id'];

        $this->completeLesson($id, '2026-08-13 16:00');
        $this->completeLesson($id, '2026-08-20 16:00');
        $this->completeLesson($id, '2026-08-27 16:00');
        $this->completeLesson($id, '2026-09-03 16:00');

        $context = $this->continuity->contextForLearner($id, $this->fixedNow('2026-09-15 12:00'));

        $this->assertNotNull($context);
        $this->assertSame('Usually drives weekly', $context['headline']);
        $this->assertStringContainsString('Nothing booked after', $context['detail']);
        $this->assertStringContainsString('Usually drives weekly', $context['line']);
        $this->assertTrue($context['needs_attention']);
    }

    public function testContextForLearnerWithFutureBookingShowsHorizonAfterLastBooked(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Booked',
            'last_name' => 'Ahead',
            'mobile' => '07700901009',
        ]);
        $id = (int) $pupil['id'];

        $this->completeLesson($id, '2026-08-13 16:00');
        $this->completeLesson($id, '2026-08-20 16:00');
        $this->completeLesson($id, '2026-08-27 16:00');
        $this->completeLesson($id, '2026-09-03 16:00');
        $this->lessons->create([
            'learner_id' => $id,
            'starts_at_local' => '2026-09-18 16:00',
        ]);

        $context = $this->continuity->contextForLearner($id, $this->fixedNow('2026-09-15 12:00'));

        $this->assertNotNull($context);
        $this->assertSame('Usually drives weekly', $context['headline']);
        $this->assertSame('Nothing booked after Friday', $context['detail']);
        $this->assertFalse($context['needs_attention']);
    }

    private function completeLesson(int $learnerId, string $local): void
    {
        $lesson = $this->lessons->create([
            'learner_id' => $learnerId,
            'starts_at_local' => $local,
        ]);
        $this->lessons->complete((int) $lesson['id'], [
            'client_mutation_id' => 'cont-' . $lesson['id'],
        ]);
    }

    private function fixedNow(string $localYmdHis): \DateTimeImmutable
    {
        return OrganisationTime::localToUtc($localYmdHis, $this->org);
    }
}
