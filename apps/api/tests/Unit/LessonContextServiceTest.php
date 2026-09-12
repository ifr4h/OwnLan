<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Lesson;
use app\models\Organisation;
use app\services\AuthService;
use app\services\FinanceService;
use app\services\LearnerService;
use app\services\LessonContextService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\NotFoundHttpException;

class LessonContextServiceTest extends Unit
{
    protected UnitTester $tester;

    private LessonContextService $context;
    private LessonService $lessons;
    private LearnerService $learners;
    private FinanceService $finance;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->context = new LessonContextService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();
        $this->finance = new FinanceService();

        (new AuthService())->register([
            'name' => 'Context Instructor',
            'email' => 'context@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->org->default_hourly_rate_pence = 3500;
        $this->org->save(false, ['default_hourly_rate_pence']);
    }

    public function testUpcomingConfirmedReturnsUsefulPanelWithoutProgress(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Jake',
            'last_name' => 'Reed',
            'mobile' => '07700904001',
            'default_pickup_address' => '12 Oak Street',
        ]);
        $this->finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 10,
            'price_pence' => 32000,
            'record_payment' => true,
        ]);

        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-20 10:00',
            'duration_minutes' => 60,
            'pickup_address' => '12 Oak Street',
        ]);

        $ctx = $this->context->forLesson((int) $lesson['id']);

        $this->assertSame('upcoming', $ctx['panel_phase']);
        $this->assertSame('Jake Reed', $ctx['pupil']['name']);
        $this->assertTrue($ctx['finance']['credit_covers_duration']);
        $this->assertSame(540, $ctx['finance']['credit_after_today_minutes']);
        $this->assertNull($ctx['teaching']['learner_next_focus']);
        $this->assertNull($ctx['teaching']['learner_last_lesson_summary']);

        $actionIds = array_column($ctx['actions']['primary'], 'id');
        $this->assertContains('move', $actionIds);
        $secondaryIds = array_column($ctx['actions']['secondary'], 'id');
        $this->assertContains('book_next', $secondaryIds);

        $creditFacts = array_filter(
            $ctx['facts'],
            static fn (array $f) => $f['id'] === 'credit',
        );
        $this->assertNotEmpty($creditFacts);
    }

    public function testUnresolvedPastSurfacesHighPriorityFact(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Past',
            'last_name' => 'Pupil',
            'mobile' => '07700904002',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-08-01 09:00',
            'duration_minutes' => 60,
        ]);

        $ctx = $this->context->forLesson((int) $lesson['id']);

        $this->assertSame('unresolved', $ctx['panel_phase']);
        $ids = array_column($ctx['facts'], 'id');
        $this->assertContains('unresolved_past', $ids);
        $this->assertSame('high', $ctx['facts'][0]['priority']);
        $primaryIds = array_column($ctx['actions']['primary'], 'id');
        $this->assertContains('complete', $primaryIds);
    }

    public function testCompletedShowsRecapAndBookNext(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Done',
            'last_name' => 'Pupil',
            'mobile' => '07700904003',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-08-10 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->complete((int) $lesson['id'], [
            'learner_summary' => 'Good mirrors',
            'next_focus' => 'Roundabouts',
        ]);

        $ctx = $this->context->forLesson((int) $lesson['id']);

        $this->assertSame('completed', $ctx['panel_phase']);
        $this->assertSame(1, $ctx['history']['completed_count']);
        $this->assertSame(0, $ctx['history']['syllabus_percent']);
        $this->assertSame('0% through syllabus', $ctx['history']['syllabus_line']);
        $primaryIds = array_column($ctx['actions']['primary'], 'id');
        $this->assertContains('recap', $primaryIds);
        $secondaryIds = array_column($ctx['actions']['secondary'], 'id');
        $this->assertContains('book_next', $secondaryIds);
    }

    public function testCancelledDoesNotInventCadence(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Harry',
            'last_name' => 'Irregular',
            'mobile' => '07700904004',
        ]);
        $id = (int) $pupil['id'];

        // Sparse irregular completed lessons — not enough for cadence.
        $a = $this->lessons->create([
            'learner_id' => $id,
            'starts_at_local' => '2026-05-01 09:00',
            'duration_minutes' => 60,
            'pickup_address' => 'A Road',
        ]);
        $this->lessons->complete((int) $a['id'], []);
        $b = $this->lessons->create([
            'learner_id' => $id,
            'starts_at_local' => '2026-06-20 14:00',
            'duration_minutes' => 90,
            'pickup_address' => 'B Road',
        ]);
        $this->lessons->complete((int) $b['id'], []);

        $lesson = $this->lessons->create([
            'learner_id' => $id,
            'starts_at_local' => '2026-09-12 11:00',
            'duration_minutes' => 60,
            'pickup_address' => 'C Road',
        ]);
        $this->lessons->cancel((int) $lesson['id'], []);

        $ctx = $this->context->forLesson((int) $lesson['id']);

        $this->assertSame('cancelled', $ctx['panel_phase']);
        $this->assertNull($ctx['continuity']['usual_cadence'] ?? null);
        $factIds = array_column($ctx['facts'], 'id');
        $this->assertNotContains('usual_slot', $factIds);
        $primaryIds = array_column($ctx['actions']['primary'], 'id');
        $this->assertContains('book_next', $primaryIds);
    }

    public function testPickupChangedFactWhenUsualExists(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'Pickup',
            'mobile' => '07700904005',
        ]);
        $id = (int) $pupil['id'];

        foreach (['2026-08-03 10:00', '2026-08-10 10:00', '2026-08-17 10:00'] as $when) {
            $row = $this->lessons->create([
                'learner_id' => $id,
                'starts_at_local' => $when,
                'duration_minutes' => 60,
                'pickup_address' => 'Usual House',
            ]);
            $this->lessons->complete((int) $row['id'], []);
        }

        $lesson = $this->lessons->create([
            'learner_id' => $id,
            'starts_at_local' => '2026-09-15 10:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Different Place',
        ]);

        $ctx = $this->context->forLesson((int) $lesson['id']);

        $this->assertTrue($ctx['pickup']['changed']);
        $this->assertSame('Usual House', $ctx['pickup']['usual_address']);
        $factIds = array_column($ctx['facts'], 'id');
        $this->assertContains('pickup_changed', $factIds);
    }

    public function testPickupNormalDoesNotClaimChanged(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Normal',
            'last_name' => 'Pickup',
            'mobile' => '07700904006',
            'default_pickup_address' => 'Home Base',
        ]);
        $id = (int) $pupil['id'];

        foreach (['2026-08-03 10:00', '2026-08-10 10:00', '2026-08-17 10:00'] as $when) {
            $row = $this->lessons->create([
                'learner_id' => $id,
                'starts_at_local' => $when,
                'duration_minutes' => 60,
                'pickup_address' => 'Home Base',
            ]);
            $this->lessons->complete((int) $row['id'], []);
        }

        $lesson = $this->lessons->create([
            'learner_id' => $id,
            'starts_at_local' => '2026-09-15 10:00',
            'duration_minutes' => 60,
            'pickup_address' => 'Home Base',
        ]);

        $ctx = $this->context->forLesson((int) $lesson['id']);

        $this->assertFalse($ctx['pickup']['changed']);
        $factIds = array_column($ctx['facts'], 'id');
        $this->assertNotContains('pickup_changed', $factIds);
    }

    public function testTenantIsolation(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Owned',
            'last_name' => 'Pupil',
            'mobile' => '07700904007',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-20 10:00',
        ]);

        (new AuthService())->register([
            'name' => 'Other Instructor',
            'email' => 'other-context@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(NotFoundHttpException::class);
        $this->context->forLesson((int) $lesson['id']);
    }

    public function testNoShowActions(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'No',
            'last_name' => 'Show',
            'mobile' => '07700904008',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-08-05 10:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->markNoShow((int) $lesson['id'], [
            'charge' => 'waived',
        ]);

        $ctx = $this->context->forLesson((int) $lesson['id']);
        $this->assertSame('no_show', $ctx['panel_phase']);
        $this->assertSame(Lesson::STATUS_NO_SHOW, $ctx['lesson']['status']);
        $primaryIds = array_column($ctx['actions']['primary'], 'id');
        $this->assertContains('book_next', $primaryIds);
    }
}
