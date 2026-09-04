<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\AvailabilityService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Yii;

/**
 * Morning Brief with a realistic busy instructor day.
 */
class MorningBriefServiceTest extends Unit
{
    protected UnitTester $tester;

    private LessonService $lessons;
    private LearnerService $learners;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Busy Instructor',
            'email' => 'brief@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
    }

    public function testBusyDaySummaryAndNeedsYou(): void
    {
        // Today's teachable day: 5 scheduled-shaped lessons (one already done)
        $p1 = $this->learners->create([
            'first_name' => 'Amy',
            'last_name' => 'Early',
            'mobile' => '07700902001',
            'default_pickup_address' => '1 Early Road LS1 1AA',
        ]);
        $p2 = $this->learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Mid',
            'mobile' => '07700902002',
            'default_pickup_address' => '2 Mid Road LS2 2BB',
        ]);
        $p3 = $this->learners->create([
            'first_name' => 'Cara',
            'last_name' => 'Now',
            'mobile' => '07700902003',
            'default_pickup_address' => '3 Now Road LS1 1AA',
        ]);
        $p4 = $this->learners->create([
            'first_name' => 'Dan',
            'last_name' => 'Late',
            'mobile' => '07700902004',
            'default_pickup_address' => '4 Late Road YO1 7HH',
        ]);
        $p5 = $this->learners->create([
            'first_name' => 'Eve',
            'last_name' => 'Last',
            'mobile' => '07700902005',
            'default_pickup_address' => '5 Last Road LS1 1AA',
        ]);

        $done = $this->lessons->create([
            'learner_id' => $p1['id'],
            'starts_at_local' => '2026-09-07 08:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->complete((int) $done['id'], [
            'next_focus' => 'Roundabouts',
            'learner_summary' => 'Good progress on mirrors',
        ]);

        $this->lessons->create([
            'learner_id' => $p2['id'],
            'starts_at_local' => '2026-09-07 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->create([
            'learner_id' => $p3['id'],
            'starts_at_local' => '2026-09-07 12:00',
            'duration_minutes' => 90,
        ]);
        $this->lessons->create([
            'learner_id' => $p4['id'],
            'starts_at_local' => '2026-09-07 14:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->create([
            'learner_id' => $p5['id'],
            'starts_at_local' => '2026-09-07 16:00',
            'duration_minutes' => 90,
        ]);

        // Tomorrow: cancellation to fill
        $sarah = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Cancel',
            'mobile' => '07700902006',
            'default_pickup_address' => 'Sarah Road LS1 1AA',
        ]);
        $yusuf = $this->learners->create([
            'first_name' => 'Yusuf',
            'last_name' => 'Fill',
            'mobile' => '07700902007',
            'default_pickup_address' => 'Yusuf Road LS1 1AA',
        ]);
        $this->learners->update((int) $yusuf['id'], [
            'test_date' => '2026-09-23',
        ]);
        $this->seedWeekly((int) $yusuf['id'], 120);
        (new AvailabilityService())->replaceForLearner((int) $yusuf['id'], [
            ['weekday' => 2, 'mode' => 'flexible'], // Tuesday 8 Sep
        ]);

        $prev = $this->learners->create([
            'first_name' => 'Prev',
            'last_name' => 'Neighbour',
            'mobile' => '07700902008',
            'default_pickup_address' => 'Prev Road LS1 1AA',
        ]);
        $this->lessons->create([
            'learner_id' => $prev['id'],
            'starts_at_local' => '2026-09-08 11:00',
            'duration_minutes' => 60,
        ]);
        $cancel = $this->lessons->create([
            'learner_id' => $sarah['id'],
            'starts_at_local' => '2026-09-08 13:00',
            'duration_minutes' => 120,
        ]);
        $this->lessons->cancel((int) $cancel['id']);

        // Continuity rebook: weekly pupil overdue by 7 Sep (last lesson 25 Aug → 13 days)
        $rebook = $this->learners->create([
            'first_name' => 'Rita',
            'last_name' => 'Rebook',
            'mobile' => '07700902009',
            'default_pickup_address' => 'Rita Road',
        ]);
        $this->seedWeekly((int) $rebook['id'], 60, '2026-07-28');

        // Mid-morning Monday 7 Sep
        $today = $this->lessons->today($this->fixedNow('2026-09-07 09:30:00'));

        $this->assertSame('Monday 7 September', $today['date_display']);
        $this->assertSame(5, $today['summary']['lesson_count']);
        $this->assertSame('08:00–17:30', $today['summary']['window_label']);
        $this->assertSame('5 lessons · 08:00–17:30', $today['summary']['line']);

        $this->assertNotNull($today['focus']);
        $this->assertSame('Ben Mid', $today['focus']['learner_name']);
        $this->assertTrue($today['focus']['is_next'] || $today['focus']['is_current']);

        $kinds = array_column($today['needs_you'], 'kind');
        $this->assertContains('empty_seat', $kinds);
        $this->assertContains('rebook', $kinds);
        $this->assertContains('test_gap', $kinds);

        $empty = null;
        foreach ($today['needs_you'] as $action) {
            if ($action['kind'] === 'empty_seat') {
                $empty = $action;
            }
        }
        $this->assertNotNull($empty);
        $this->assertStringContainsString('cancellation', $empty['title']);
        $this->assertSame('View matches', $empty['cta_label']);
        $this->assertGreaterThanOrEqual(1, $empty['match_count']);

        $rebookAction = null;
        foreach ($today['needs_you'] as $action) {
            if ($action['kind'] === 'rebook' && str_contains((string) $action['title'], 'Rita')) {
                $rebookAction = $action;
            }
        }
        $this->assertNotNull($rebookAction);
        $this->assertSame('Book', $rebookAction['cta_label']);
        $this->assertStringContainsString('weekly', (string) $rebookAction['detail']);

        $testGap = null;
        foreach ($today['needs_you'] as $action) {
            if ($action['kind'] === 'test_gap' && str_contains((string) $action['title'], 'Yusuf')) {
                $testGap = $action;
            }
        }
        $this->assertNotNull($testGap);
        $this->assertSame('No lesson booked next week', $testGap['detail']);
        $this->assertSame('Book', $testGap['cta_label']);
    }

    private function seedWeekly(int $learnerId, int $duration, string $firstDay = '2026-08-04'): void
    {
        for ($i = 0; $i < 5; $i++) {
            $day = (new DateTimeImmutable($firstDay))->modify('+' . ($i * 7) . ' days')->format('Y-m-d');
            $lesson = $this->lessons->create([
                'learner_id' => $learnerId,
                'starts_at_local' => $day . ' 10:00',
                'duration_minutes' => $duration,
            ]);
            Yii::$app->db->createCommand()->update('lessons', [
                'status' => 'completed',
                'completed_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $lesson['id']])->execute();
        }
    }

    private function fixedNow(string $localYmdHis): DateTimeImmutable
    {
        return OrganisationTime::localToUtc($localYmdHis, $this->org);
    }
}
