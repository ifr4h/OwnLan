<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\LearnerAvailability;
use app\services\AuthService;
use app\services\AvailabilityService;
use app\services\GapMatchingService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\FakeTravelProvider;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;
use Yii;

class GapMatchingServiceTest extends Unit
{
    protected UnitTester $tester;

    private LearnerService $learners;
    private LessonService $lessons;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->learners = new LearnerService();
        $this->lessons = new LessonService();
    }

    public function testRanksDuePupilWithTravelReason(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'gap@example.com',
            'password' => 'password123',
        ]);

        $prev = $this->learners->create([
            'first_name' => 'Prev',
            'last_name' => 'Pupil',
            'mobile' => '07700900001',
            'default_pickup_address' => 'Anchor LS1 1AA',
        ]);
        $next = $this->learners->create([
            'first_name' => 'Next',
            'last_name' => 'Pupil',
            'mobile' => '07700900002',
            'default_pickup_address' => 'Later LS1 2BB',
        ]);
        $maryam = $this->learners->create([
            'first_name' => 'Maryam',
            'last_name' => 'Ali',
            'mobile' => '07700900003',
            'default_pickup_address' => 'Maryam Home LS1 1AA',
        ]);
        $adam = $this->learners->create([
            'first_name' => 'Adam',
            'last_name' => 'Brown',
            'mobile' => '07700900004',
            'default_pickup_address' => 'Adam Home LS2 3CC',
        ]);

        // Seed completed history so Maryam is due (weekly pattern overdue).
        $this->seedCompletedWeekly((int) $maryam['id'], '2026-08-06', 5);
        // Adam has recent history — not due — but Thursday availability.
        $lesson = $this->lessons->create([
            'learner_id' => $adam['id'],
            'starts_at_local' => '2026-09-14 10:00',
            'duration_minutes' => 60,
        ]);
        Yii::$app->db->createCommand()->update('lessons', [
            'status' => 'completed',
            'completed_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $lesson['id']])->execute();

        (new AvailabilityService())->replaceForLearner((int) $adam['id'], [
            ['weekday' => 4, 'mode' => 'between', 'start_time' => '09:00', 'end_time' => '15:00'],
        ]);

        // Thursday 2026-09-17: lesson 10:00–11:00 and 13:00–14:00 → 2h gap 11:00–13:00
        $this->lessons->create([
            'learner_id' => $prev['id'],
            'starts_at_local' => '2026-09-17 10:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Anchor LS1 1AA',
        ]);
        $this->lessons->create([
            'learner_id' => $next['id'],
            'starts_at_local' => '2026-09-17 13:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Later LS1 2BB',
        ]);

        $fake = new FakeTravelProvider([
            'anchor ls1 1aa|maryam home ls1 1aa' => 7,
            'maryam home ls1 1aa|later ls1 2bb' => 8,
            'anchor ls1 1aa|adam home ls2 3cc' => 11,
            'adam home ls2 3cc|later ls1 2bb' => 12,
        ]);

        $diary = $this->lessons->diary(
            'day',
            '2026-09-17',
            new DateTimeImmutable('2026-09-17 09:00:00', new DateTimeZone('UTC')),
        );
        // Use service directly with fake travel for ranking assertions
        $matcher = new GapMatchingService($fake);
        $org = \app\models\Organisation::find()->one();
        $this->assertNotNull($org);
        $gaps = $matcher->gapsForDay(
            $org,
            '2026-09-17',
            $diary['days'][0]['lessons'],
            new DateTimeImmutable('2026-09-17 09:00:00', new DateTimeZone('UTC')),
        );

        $this->assertCount(1, $gaps);
        $this->assertSame(120, $gaps[0]['duration_minutes']);
        $this->assertSame('2 hour gap', $gaps[0]['label']);
        $this->assertGreaterThanOrEqual(2, count($gaps[0]['matches']));

        $names = array_column($gaps[0]['matches'], 'learner_name');
        $this->assertSame('Maryam Ali', $names[0]);
        $this->assertContains('Due another lesson', $gaps[0]['matches'][0]['reasons']);
        $this->assertContains('7 min from previous lesson', $gaps[0]['matches'][0]['reasons']);

        $adamMatch = null;
        foreach ($gaps[0]['matches'] as $match) {
            if ($match['learner_name'] === 'Adam Brown') {
                $adamMatch = $match;
            }
        }
        $this->assertNotNull($adamMatch);
        $this->assertContains('Available', $adamMatch['reasons']);
        $this->assertContains('11 min from previous lesson', $adamMatch['reasons']);
    }

    public function testExcludesAvailabilityMismatchAndFutureBooking(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'gap2@example.com',
            'password' => 'password123',
        ]);

        $prev = $this->learners->create([
            'first_name' => 'Prev',
            'last_name' => 'A',
            'mobile' => '07700900011',
            'default_pickup_address' => 'A LS1 1AA',
        ]);
        $next = $this->learners->create([
            'first_name' => 'Next',
            'last_name' => 'B',
            'mobile' => '07700900012',
            'default_pickup_address' => 'B LS1 2BB',
        ]);
        $busy = $this->learners->create([
            'first_name' => 'Busy',
            'last_name' => 'Bee',
            'mobile' => '07700900013',
            'default_pickup_address' => 'C LS1 1AA',
        ]);
        $wrongDay = $this->learners->create([
            'first_name' => 'Wrong',
            'last_name' => 'Window',
            'mobile' => '07700900014',
            'default_pickup_address' => 'D LS1 1AA',
        ]);

        $this->seedCompletedSparse((int) $busy['id']);
        $this->seedCompletedSparse((int) $wrongDay['id']);

        // Busy already has a future lesson
        $this->lessons->create([
            'learner_id' => $busy['id'],
            'starts_at_local' => '2026-09-20 10:00',
            'duration_minutes' => 60,
        ]);

        (new AvailabilityService())->replaceForLearner((int) $wrongDay['id'], [
            ['weekday' => 4, 'mode' => 'before', 'end_time' => '10:00'],
        ]);

        $this->lessons->create([
            'learner_id' => $prev['id'],
            'starts_at_local' => '2026-09-17 10:00',
            'duration_minutes' => 60,
            'pickup_address' => 'A LS1 1AA',
        ]);
        $this->lessons->create([
            'learner_id' => $next['id'],
            'starts_at_local' => '2026-09-17 13:00',
            'duration_minutes' => 60,
            'pickup_address' => 'B LS1 2BB',
        ]);

        $fake = new FakeTravelProvider([
            'a ls1 1aa|c ls1 1aa' => 5,
            'c ls1 1aa|b ls1 2bb' => 5,
            'a ls1 1aa|d ls1 1aa' => 5,
            'd ls1 1aa|b ls1 2bb' => 5,
        ]);
        $matcher = new GapMatchingService($fake);
        $org = \app\models\Organisation::find()->one();
        $diary = $this->lessons->diary(
            'day',
            '2026-09-17',
            new DateTimeImmutable('2026-09-17 08:00:00', new DateTimeZone('UTC')),
        );
        $gaps = $matcher->gapsForDay(
            $org,
            '2026-09-17',
            $diary['days'][0]['lessons'],
            new DateTimeImmutable('2026-09-17 08:00:00', new DateTimeZone('UTC')),
        );

        $names = array_column($gaps[0]['matches'] ?? [], 'learner_name');
        $this->assertNotContains('Busy Bee', $names);
        $this->assertNotContains('Wrong Window', $names);
    }

    public function testIgnoresShortGaps(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'gap3@example.com',
            'password' => 'password123',
        ]);
        $a = $this->learners->create([
            'first_name' => 'A',
            'last_name' => 'One',
            'mobile' => '07700900021',
            'default_pickup_address' => 'A',
        ]);
        $b = $this->learners->create([
            'first_name' => 'B',
            'last_name' => 'Two',
            'mobile' => '07700900022',
            'default_pickup_address' => 'B',
        ]);
        $this->lessons->create([
            'learner_id' => $a['id'],
            'starts_at_local' => '2026-09-17 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->create([
            'learner_id' => $b['id'],
            'starts_at_local' => '2026-09-17 11:30',
            'duration_minutes' => 60,
        ]);

        $diary = $this->lessons->diary(
            'day',
            '2026-09-17',
            new DateTimeImmutable('2026-09-17 08:00:00', new DateTimeZone('UTC')),
        );
        $this->assertSame([], $diary['days'][0]['gaps']);
        $this->assertSame(0, $diary['gap_count']);
    }

    private function seedCompletedWeekly(int $learnerId, string $firstDate, int $count): void
    {
        // Create completed lessons going back — use create then force status
        for ($i = 0; $i < $count; $i++) {
            $day = (new DateTimeImmutable($firstDate))->modify('+' . ($i * 7) . ' days')->format('Y-m-d');
            $lesson = $this->lessons->create([
                'learner_id' => $learnerId,
                'starts_at_local' => $day . ' 10:00',
                'duration_minutes' => 60,
            ]);
            Yii::$app->db->createCommand()->update('lessons', [
                'status' => 'completed',
                'completed_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $lesson['id']])->execute();
        }
    }

    private function seedCompletedSparse(int $learnerId): void
    {
        // Two completed lessons > soft gap ago so they can appear when available
        foreach (['2026-08-01', '2026-08-20'] as $day) {
            $lesson = $this->lessons->create([
                'learner_id' => $learnerId,
                'starts_at_local' => $day . ' 10:00',
                'duration_minutes' => 60,
            ]);
            Yii::$app->db->createCommand()->update('lessons', [
                'status' => 'completed',
                'completed_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $lesson['id']])->execute();
        }
    }
}
