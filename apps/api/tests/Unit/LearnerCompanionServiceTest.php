<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\EncodedPolyline;
use app\components\TenantContext;
use app\models\Organisation;
use app\models\ProgressSkill;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonRouteService;
use app\services\LessonService;
use app\services\PortalAuthService;
use app\services\PortalHomeService;
use app\services\ProgressService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class LearnerCompanionServiceTest extends Unit
{
    protected UnitTester $tester;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, lesson_routes, lesson_skills, learner_skill_progress, learner_portal_accounts, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        TenantContext::clear();

        (new AuthService())->register([
            'name' => 'Companion Instructor',
            'email' => 'companion@example.com',
            'password' => 'password123',
        ]);
        $org = Organisation::find()->one();
        $org->default_hourly_rate_pence = 3500;
        $org->save(false, ['default_hourly_rate_pence']);
    }

    public function testEncodedPolylineRoundTrip(): void
    {
        $points = [[51.9942, -0.7435], [52.0012, -0.7355], [52.0088, -0.7220]];
        $encoded = EncodedPolyline::encode($points);
        $decoded = EncodedPolyline::decode($encoded);
        $this->assertCount(3, $decoded);
        $this->assertEqualsWithDelta($points[0][0], $decoded[0][0], 0.0001);
        $this->assertEqualsWithDelta($points[1][1], $decoded[1][1], 0.0001);
        $this->assertGreaterThan(0, EncodedPolyline::approximateDistanceMetres($points));
    }

    public function testSkillProgressAndInsightsOnPortal(): void
    {
        $this->assertGreaterThan(10, ProgressSkill::find()->count());

        $learners = new LearnerService();
        $lessons = new LessonService();
        $pupil = $learners->create([
            'first_name' => 'Amina',
            'last_name' => 'Demo',
            'mobile' => '07700906001',
            'email' => 'amina-demo@example.com',
        ]);

        $roundabout = ProgressSkill::findOne(['code' => 'roundabouts']);
        $this->assertNotNull($roundabout);

        foreach (['introduced', 'practising', 'developing'] as $i => $rating) {
            $lesson = $lessons->create([
                'learner_id' => $pupil['id'],
                'starts_at_local' => sprintf('2026-02-%02d 10:00', 10 + $i),
                'duration_minutes' => 90,
            ]);
            $lessons->complete((int) $lesson['id'], [
                'learner_summary' => 'Worked on roundabouts',
                'next_focus' => 'Lane choice',
                'skill_ids' => [(int) $roundabout->id],
                'skill_ratings' => [(int) $roundabout->id => $rating],
                'settlement' => 'charge',
            ]);
        }

        $portalAuth = new PortalAuthService();
        $invite = $portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $portalAuth->activate(['token' => $query['token'], 'password' => 'learnerpass1']);

        $home = (new PortalHomeService())->home();
        $this->assertArrayHasKey('journey', $home);
        $this->assertSame(3, $home['journey']['lessons_completed']);
        $this->assertSame(4.5, $home['journey']['total_hours']);
        $this->assertNotEmpty($home['progress']['insights']);
        $this->assertArrayHasKey('theory', $home);
        $this->assertArrayHasKey('greeting', $home);

        $progress = (new PortalHomeService())->progress();
        $this->assertArrayHasKey('categories', $progress);
        $this->assertNotEmpty($progress['practised_counts']);
    }

    public function testRouteRecordingShareAndPortalVisibility(): void
    {
        $learners = new LearnerService();
        $lessons = new LessonService();
        $routes = new LessonRouteService();

        $pupil = $learners->create([
            'first_name' => 'Route',
            'last_name' => 'Learner',
            'mobile' => '07700906002',
            'email' => 'route-learner@example.com',
        ]);
        $lesson = $lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-03-01 11:00',
            'duration_minutes' => 60,
        ]);

        $started = $routes->start((int) $lesson['id']);
        $this->assertSame('recording', $started['status']);

        $stopped = $routes->stop((int) $lesson['id'], [
            'points' => [
                ['lat' => 51.9942, 'lng' => -0.7435],
                ['lat' => 51.9968, 'lng' => -0.7401],
                ['lat' => 52.0012, 'lng' => -0.7355],
            ],
        ]);
        $this->assertSame('completed', $stopped['status']);
        $this->assertFalse($stopped['learner_visible']);

        $portalAuth = new PortalAuthService();
        $invite = $portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $portalAuth->activate(['token' => $query['token'], 'password' => 'learnerpass1']);

        $this->assertSame([], $routes->listForPortalLearner());

        // Instructor must share — switch back to instructor session
        Yii::$app->portalUser->logout();
        (new AuthService())->login([
            'email' => 'companion@example.com',
            'password' => 'password123',
        ]);

        $shared = $routes->shareWithLearner((int) $lesson['id']);
        $this->assertTrue($shared['learner_visible']);

        Yii::$app->user->logout();
        TenantContext::clear();
        $portalAuth->login([
            'email' => 'route-learner@example.com',
            'password' => 'learnerpass1',
        ]);

        $list = $routes->listForPortalLearner();
        $this->assertCount(1, $list);
        $detail = $routes->portalDetail((int) $list[0]['id']);
        $this->assertNotEmpty($detail['encoded_polyline']);
    }

    public function testProgressCatalogueGrouped(): void
    {
        $categories = (new ProgressService())->catalogue();
        $codes = array_column($categories, 'code');
        $this->assertContains('junctions', $codes);
        $this->assertContains('independent', $codes);
    }
}
