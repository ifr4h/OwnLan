<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonService;
use app\services\TravelFeasibilityService;
use app\tests\Support\FakeTravelProvider;
use app\tests\Support\UnitTester;
use app\travel\HeuristicTravelProvider;
use Codeception\Test\Unit;
use Yii;

class TravelFeasibilityServiceTest extends Unit
{
    protected UnitTester $tester;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
    }

    public function testSeverityLevelsIndependentOfProvider(): void
    {
        $service = new TravelFeasibilityService(new FakeTravelProvider());

        $this->assertSame('impossible', $service->severity(10, 18));
        $this->assertSame('tight', $service->severity(20, 18));
        $this->assertSame('ok', $service->severity(30, 18));
    }

    public function testAssessGapUsesProviderAndBuildsExampleMessage(): void
    {
        $fake = new FakeTravelProvider([
            'station road|college gate' => 18,
        ]);
        $service = new TravelFeasibilityService($fake);

        $result = $service->assessGap('Station Road', 'College Gate', 10, 'Amy', 'Ben');

        $this->assertNotNull($result);
        $this->assertSame('impossible', $result['severity']);
        $this->assertSame(10, $result['available_minutes']);
        $this->assertSame(18, $result['travel_minutes']);
        $this->assertTrue($result['is_warning']);
        $this->assertSame(
            'Only 10 minutes between these lessons. Estimated drive: 18 minutes.',
            $result['message'],
        );
    }

    public function testMissingAddressYieldsNoAssessment(): void
    {
        $service = new TravelFeasibilityService(new FakeTravelProvider([
            'a|b' => 20,
        ]));
        $this->assertNull($service->assessGap(null, 'b', 5));
        $this->assertNull($service->assessGap('a', '', 5));
    }

    public function testAnnotateDiaryMarksTightLeg(): void
    {
        $fake = new FakeTravelProvider([
            'home|school' => 15,
        ]);
        $service = new TravelFeasibilityService($fake);

        $items = [
            [
                'id' => 1,
                'status' => 'scheduled',
                'starts_at' => '2026-09-15T09:00:00+00:00',
                'ends_at' => '2026-09-15T10:00:00+00:00',
                'pickup_address' => 'Home',
                'learner_name' => 'A',
            ],
            [
                'id' => 2,
                'status' => 'scheduled',
                'starts_at' => '2026-09-15T10:20:00+00:00',
                'ends_at' => '2026-09-15T11:20:00+00:00',
                'pickup_address' => 'School',
                'learner_name' => 'B',
            ],
        ];

        $annotated = $service->annotateDiaryItems($items);
        $leg = $annotated[0]['travel_to_next'];
        $this->assertIsArray($leg);
        $this->assertSame(20, $leg['available_minutes']);
        $this->assertSame(15, $leg['travel_minutes']);
        $this->assertSame('tight', $leg['severity']);
        $this->assertTrue($leg['is_warning']);
        $this->assertNull($annotated[1]['travel_to_next']);
    }

    public function testCheckProposedAgainstNeighbours(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'travel@example.com',
            'password' => 'password123',
        ]);
        $learners = new LearnerService();
        $lessons = new LessonService();

        $amy = $learners->create([
            'first_name' => 'Amy',
            'last_name' => 'One',
            'mobile' => '07700900111',
            'default_pickup_address' => 'Station Road LS1 1AA',
        ]);
        $learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Two',
            'mobile' => '07700900222',
            'default_pickup_address' => 'College Gate LS6 2AB',
        ]);

        $lessons->create([
            'learner_id' => $amy['id'],
            'starts_at_local' => '2026-09-15 10:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Station Road LS1 1AA',
        ]);

        // Amy 10:00–11:00 London → 09:00–10:00 UTC. Propose 10:10 UTC (10 min gap).
        $fake = new FakeTravelProvider([
            'station road ls1 1aa|college gate ls6 2ab' => 18,
        ]);
        $travel = new TravelFeasibilityService($fake);
        $org = \app\models\Organisation::find()->one();
        $this->assertNotNull($org);

        $warnings = $travel->checkProposed(
            $org,
            '2026-09-15 10:10:00',
            60,
            'College Gate LS6 2AB',
            'Ben Two',
            null,
        );

        $this->assertCount(1, $warnings);
        $this->assertSame('impossible', $warnings[0]['severity']);
        $this->assertSame(10, $warnings[0]['available_minutes']);
        $this->assertSame(18, $warnings[0]['travel_minutes']);
        $this->assertSame('from_previous', $warnings[0]['direction']);
    }

    public function testHeuristicSameAndDistinctAddresses(): void
    {
        $provider = new HeuristicTravelProvider();
        $this->assertSame(0, $provider->estimateDriveMinutes('12 High Street', '12 High Street'));
        $this->assertSame(5, $provider->estimateDriveMinutes('1 Road LS1 1AA', '2 Road LS1 1AA'));
        $this->assertSame(12, $provider->estimateDriveMinutes('A LS1 1AA', 'B LS1 2BB'));
        $this->assertSame(22, $provider->estimateDriveMinutes('Leeds LS1 1AA', 'York YO1 7HH'));
        $this->assertNull($provider->estimateDriveMinutes(null, 'Somewhere'));
    }

    public function testCreateDoesNotBlockOnTravelWarning(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'travel2@example.com',
            'password' => 'password123',
        ]);
        $learners = new LearnerService();
        $lessons = new LessonService();

        $amy = $learners->create([
            'first_name' => 'Amy',
            'last_name' => 'One',
            'mobile' => '07700900333',
            'default_pickup_address' => 'Place A LS2 1AA',
        ]);
        $ben = $learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Two',
            'mobile' => '07700900444',
            'default_pickup_address' => 'Place B YO1 7HH',
        ]);

        $lessons->create([
            'learner_id' => $amy['id'],
            'starts_at_local' => '2026-09-15 10:00',
            'duration_minutes' => 60,
        ]);

        $created = $lessons->create([
            'learner_id' => $ben['id'],
            'starts_at_local' => '2026-09-15 11:10',
            'duration_minutes' => 60,
        ]);

        $this->assertSame('scheduled', $created['status']);
        $this->assertArrayHasKey('travel_warnings', $created);
        $this->assertNotEmpty($created['travel_warnings']);
        $this->assertSame('impossible', $created['travel_warnings'][0]['severity']);
    }
}
