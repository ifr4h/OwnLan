<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\GapMatchingService;
use app\services\LearnerService;
use app\services\LessonService;
use app\services\PupilAttentionService;
use app\tests\Support\FakeTravelProvider;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class PupilAttentionServiceTest extends Unit
{
    protected UnitTester $tester;

    private PupilAttentionService $attention;
    private LearnerService $learners;
    private LessonService $lessons;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->learners = new LearnerService();
        $this->lessons = new LessonService();

        (new AuthService())->register([
            'name' => 'Attention Instructor',
            'email' => 'attention@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->attention = new PupilAttentionService();
    }

    public function testListSummaryUsesPlainEnglish(): void
    {
        $sarah = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Weekly',
            'mobile' => '07700902001',
        ]);
        $id = (int) $sarah['id'];
        $this->seedCompletedWeekly($id, '2026-08-06', 5);

        $this->learners->create([
            'first_name' => 'Test',
            'last_name' => 'Soon',
            'mobile' => '07700902002',
        ]);
        Yii::$app->db->createCommand()->update('learners', [
            'test_date' => '2026-09-20',
        ], ['mobile' => '07700902002'])->execute();

        $owed = $this->learners->create([
            'first_name' => 'Owen',
            'last_name' => 'Owes',
            'mobile' => '07700902003',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => (int) $owed['id'],
            'starts_at_local' => '2026-09-10 11:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->complete((int) $lesson['id'], ['settlement' => 'charge']);

        $items = $this->learners->listActive();
        $result = $this->attention->enrichActiveList($items, $this->fixedNow('2026-09-15 12:00'));

        $this->assertContains('1 pupil hasn\'t booked another lesson', $result['attention']['lines']);
        $this->assertContains('1 test in the next 14 days', $result['attention']['lines']);
        $this->assertStringContainsString('still to collect', implode(' ', $result['attention']['lines']));

        $sarahRow = null;
        foreach ($result['items'] as $row) {
            if ((int) $row['id'] === $id) {
                $sarahRow = $row;
                break;
            }
        }
        $this->assertNotNull($sarahRow);
        $this->assertStringContainsString('Usually weekly', (string) $sarahRow['attention_hint']);
    }

    public function testWaitingListGetsGapMatchSummary(): void
    {
        $prev = $this->learners->create([
            'first_name' => 'Prev',
            'last_name' => 'Pupil',
            'mobile' => '07700902010',
            'default_pickup_address' => 'Anchor LS1 1AA',
        ]);
        $next = $this->learners->create([
            'first_name' => 'Next',
            'last_name' => 'Pupil',
            'mobile' => '07700902011',
            'default_pickup_address' => 'Later LS1 2BB',
        ]);
        $waiting = $this->learners->create([
            'first_name' => 'Wait',
            'last_name' => 'Listed',
            'mobile' => '07700902012',
            'default_pickup_address' => 'Wait Home LS1 1AA',
        ]);
        Yii::$app->db->createCommand()->update('learners', [
            'lifecycle' => 'waiting',
            'waiting_list_joined_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => (int) $waiting['id']])->execute();

        $this->lessons->create([
            'learner_id' => (int) $prev['id'],
            'starts_at_local' => '2026-09-15 09:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Anchor LS1 1AA',
        ]);
        $this->lessons->create([
            'learner_id' => (int) $next['id'],
            'starts_at_local' => '2026-09-15 12:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Later LS1 2BB',
        ]);

        $fake = new FakeTravelProvider([
            'anchor ls1 1aa|wait home ls1 1aa' => 7,
            'wait home ls1 1aa|later ls1 2bb' => 8,
        ]);
        $attention = new PupilAttentionService(null, null, new GapMatchingService($fake));

        $items = $this->learners->listWaiting();
        $result = $attention->enrichWaitingList($items, $this->fixedNow('2026-09-15 08:00'));

        $this->assertCount(1, $result['items']);
        $this->assertArrayHasKey('gap_matches', $result['items'][0]);
        $this->assertGreaterThan(0, $result['items'][0]['gap_matches']['match_count']);
        $this->assertNotNull($result['items'][0]['gap_matches']['summary']);
    }

    private function seedCompletedWeekly(int $learnerId, string $firstWednesday, int $count): void
    {
        $cursor = new \DateTimeImmutable($firstWednesday);
        for ($i = 0; $i < $count; $i++) {
            $lesson = $this->lessons->create([
                'learner_id' => $learnerId,
                'starts_at_local' => $cursor->format('Y-m-d H:i'),
            ]);
            $this->lessons->complete((int) $lesson['id'], [
                'client_mutation_id' => 'seed-' . $learnerId . '-' . $i,
            ]);
            $cursor = $cursor->modify('+7 days');
        }
    }

    private function fixedNow(string $localYmdHis): \DateTimeImmutable
    {
        return OrganisationTime::localToUtc($localYmdHis, $this->org);
    }
}
