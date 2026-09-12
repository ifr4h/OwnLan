<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\services\AuthService;
use app\services\LearnerService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class LearnerServiceTest extends Unit
{
    protected UnitTester $tester;

    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->learners = new LearnerService();
    }

    public function testCreateListGetUpdateAndArchive(): void
    {
        (new AuthService())->register([
            'name' => 'Instructor One',
            'email' => 'one@example.com',
            'password' => 'password123',
        ]);

        $created = $this->learners->create([
            'first_name' => 'Mia',
            'last_name' => 'Patel',
            'mobile' => '07700 900123',
            'email' => 'mia@example.com',
            'default_pickup_address' => '12 High Street, Leeds',
        ]);

        $this->assertSame('Mia Patel', $created['full_name']);
        $this->assertNull($created['test_date']);
        $this->assertSame('active', $created['lifecycle']);

        $list = $this->learners->listActive('patel');
        $this->assertCount(1, $list);

        $updated = $this->learners->update((int) $created['id'], [
            'test_date' => '2026-11-15',
            'test_centre' => 'Leeds',
            'private_notes' => 'Prefers mornings',
        ]);
        $this->assertSame('2026-11-15', $updated['test_date']);
        $this->assertSame('Leeds', $updated['test_centre']);

        $archived = $this->learners->archive((int) $created['id']);
        $this->assertNotNull($archived['archived_at']);
        $this->assertSame('inactive', $archived['status']);
        $this->assertCount(0, $this->learners->listActive());
    }

    public function testPausePassedAndReactivate(): void
    {
        (new AuthService())->register([
            'name' => 'Instructor Status',
            'email' => 'status@example.com',
            'password' => 'password123',
        ]);

        $pupil = $this->learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Lane',
            'mobile' => '07700901111',
        ]);
        $id = (int) $pupil['id'];

        $paused = $this->learners->setStatus($id, 'paused');
        $this->assertSame('paused', $paused['status']);
        $this->assertSame('Paused', $paused['status_label']);
        $this->assertNull($paused['archived_at']);
        $this->assertCount(0, $this->learners->listActive());
        $this->assertCount(1, $this->learners->listByStatus('paused'));
        $this->assertCount(1, $this->learners->listByStatus('all'));

        $passed = $this->learners->setStatus($id, 'passed');
        $this->assertSame('passed', $passed['status']);
        $this->assertCount(1, $this->learners->listByStatus('passed'));
        $this->assertCount(0, $this->learners->listByStatus('paused'));
        $this->assertCount(1, $this->learners->listByStatus('all'));

        $waiting = $this->learners->setStatus($id, 'waiting');
        $this->assertSame('waiting', $waiting['status']);
        $this->assertNotNull($waiting['waiting_list_joined_at']);

        $active = $this->learners->setStatus($id, 'active');
        $this->assertSame('active', $active['status']);
        $this->assertNull($active['waiting_list_joined_at']);

        $inactive = $this->learners->setStatus($id, 'inactive');
        $this->assertSame('inactive', $inactive['status']);
        $this->assertNotNull($inactive['archived_at']);

        $reactivated = $this->learners->setStatus($id, 'active');
        $this->assertSame('active', $reactivated['status']);
        $this->assertNull($reactivated['archived_at']);

        $counts = $this->learners->statusCounts();
        $this->assertSame(1, $counts['active']);
        $this->assertSame(0, $counts['paused']);
        $this->assertSame(0, $counts['inactive']);
    }

    public function testTenantIsolationOnLearners(): void
    {
        $auth = new AuthService();
        $auth->register([
            'name' => 'Instructor A',
            'email' => 'a@example.com',
            'password' => 'password123',
        ]);
        $pupilA = $this->learners->create([
            'first_name' => 'Asha',
            'last_name' => 'Green',
            'mobile' => '07700900111',
        ]);
        $auth->logout();

        $auth->register([
            'name' => 'Instructor B',
            'email' => 'b@example.com',
            'password' => 'password123',
        ]);
        $this->learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Brown',
            'mobile' => '07700900222',
        ]);

        $this->assertCount(1, $this->learners->listActive());
        $this->assertSame('Ben Brown', $this->learners->listActive()[0]['full_name']);

        $this->expectException(\yii\web\NotFoundHttpException::class);
        $this->learners->get((int) $pupilA['id']);
    }

    public function testLearnerReportCountsNewAndActive(): void
    {
        (new AuthService())->register([
            'name' => 'Instructor Report',
            'email' => 'report@example.com',
            'password' => 'password123',
        ]);

        $this->learners->create([
            'first_name' => 'Amy',
            'last_name' => 'New',
            'mobile' => '07700903333',
        ]);
        $passed = $this->learners->create([
            'first_name' => 'Pat',
            'last_name' => 'Pass',
            'mobile' => '07700904444',
        ]);
        $this->learners->setStatus((int) $passed['id'], 'passed');

        $report = $this->learners->report(
            (new \DateTimeImmutable('first day of this month'))->format('Y-m-d'),
            (new \DateTimeImmutable('now'))->format('Y-m-d'),
        );

        $this->assertSame(1, $report['learner_count']);
        $this->assertSame(2, $report['new_learners']);
        $this->assertSame(1, $report['passed']);
        $this->assertNotEmpty($report['series']);
        $this->assertSame('New learners', $report['breakdown'][0]['label']);
    }

    public function testUnauthenticatedAccessDenied(): void
    {
        $this->expectException(\yii\web\UnauthorizedHttpException::class);
        $this->learners->listActive();
    }
}
