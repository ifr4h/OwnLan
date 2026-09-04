<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\LearnerSkillProgress;
use app\models\Organisation;
use app\models\PrivatePracticeSession;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LearnService;
use app\services\LessonRouteService;
use app\services\LessonService;
use app\services\PortalAuthService;
use app\services\TeachingStudioService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class LearningEngineServiceTest extends Unit
{
    protected UnitTester $tester;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, learning_activities, temporary_shares, learner_companions, companion_accounts, lesson_resources, route_moments, private_practice_sessions, teaching_resources, lesson_routes, lesson_skills, learner_skill_progress, learner_portal_accounts, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        if (Yii::$app->has('companionUser')) {
            Yii::$app->companionUser->logout();
        }
        TenantContext::clear();

        (new AuthService())->register([
            'name' => 'Engine Instructor',
            'email' => 'engine@example.com',
            'password' => 'password123',
        ]);
        $org = Organisation::find()->one();
        $org->default_hourly_rate_pence = 3500;
        $org->save(false, ['default_hourly_rate_pence']);
    }

    public function testMasterAttachDoesNotOverwriteMaster(): void
    {
        $studio = new TeachingStudioService();
        $master = $studio->create([
            'title' => 'Kingston Roundabout',
            'kind' => 'board',
            'template_code' => 'roundabout',
            'category' => 'roundabouts',
            'skill_codes' => ['roundabouts'],
            'scene' => [
                'template' => 'roundabout',
                'version' => 1,
                'objects' => [['id' => 'a', 'type' => 'learner_car', 'x' => 0.5, 'y' => 0.5, 'rotation' => 0]],
                'strokes' => [],
                'steps' => [['id' => 's1', 'label' => 'Approach', 'objects' => [], 'strokes' => [], 'note' => 'Master note']],
                'active_step' => 0,
                'map' => null,
            ],
        ]);

        $learners = new LearnerService();
        $lessons = new LessonService();
        $pupil = $learners->create([
            'first_name' => 'Amina',
            'last_name' => 'Engine',
            'mobile' => '07700907001',
            'email' => 'amina-engine@example.com',
        ]);
        $lesson = $lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-04-01 10:00',
            'duration_minutes' => 90,
        ]);

        $attached = $studio->attachToLesson((int) $lesson['id'], [
            'source_resource_id' => $master['id'],
            'learner_visible_note' => 'Lesson-specific tip',
            'scene' => [
                'template' => 'roundabout',
                'version' => 1,
                'objects' => [['id' => 'a', 'type' => 'learner_car', 'x' => 0.4, 'y' => 0.6, 'rotation' => 15]],
                'strokes' => [],
                'steps' => [['id' => 's1', 'label' => 'Approach', 'objects' => [], 'strokes' => [], 'note' => 'Sarah note']],
                'active_step' => 0,
                'map' => null,
            ],
        ]);

        $freshMaster = $studio->get((int) $master['id']);
        $this->assertSame('Master note', $freshMaster['scene']['steps'][0]['note']);
        $this->assertSame('Sarah note', $attached['scene']['steps'][0]['note']);
        $this->assertSame('Lesson-specific tip', $attached['learner_visible_note']);
    }

    public function testPrivatePracticeDoesNotMutateInstructorRatings(): void
    {
        $learners = new LearnerService();
        $pupil = $learners->create([
            'first_name' => 'Priya',
            'last_name' => 'Practice',
            'mobile' => '07700907002',
            'email' => 'priya-practice@example.com',
        ]);

        $portal = new PortalAuthService();
        $invite = $portal->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $portal->activate(['token' => $query['token'], 'password' => 'learnerpass1']);

        $before = (int) LearnerSkillProgress::find()->count();
        (new LearnService())->logPrivatePractice([
            'duration_minutes' => 45,
            'skill_codes' => ['roundabouts'],
            'feeling' => PrivatePracticeSession::FEELING_OKAY,
        ]);
        $after = (int) LearnerSkillProgress::find()->count();
        $this->assertSame($before, $after);

        $list = (new LearnService())->portalPracticeList();
        $this->assertCount(1, $list);
        $this->assertSame(45, $list[0]['duration_minutes']);
    }

    public function testLearnPersonalisationAndMomentVisibility(): void
    {
        $learners = new LearnerService();
        $lessons = new LessonService();
        $routes = new LessonRouteService();

        $pupil = $learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Learn',
            'mobile' => '07700907003',
            'email' => 'sam-learn@example.com',
        ]);
        $learnerRow = \app\models\Learner::findOne((int) $pupil['id']);
        $this->assertNotNull($learnerRow);
        $learnerRow->next_focus = 'Independent lane choice on spiral roundabouts';
        $learnerRow->save(false, ['next_focus']);

        $lesson = $lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-04-02 11:00',
            'duration_minutes' => 60,
        ]);
        $routes->start((int) $lesson['id']);
        $routes->stop((int) $lesson['id'], [
            'points' => [
                ['lat' => 51.9942, 'lng' => -0.7435],
                ['lat' => 52.0012, 'lng' => -0.7355],
                ['lat' => 52.0088, 'lng' => -0.7220],
            ],
        ]);

        $momentSvc = new \app\services\RouteMomentService();
        $moment = $momentSvc->mark((int) $lesson['id'], [
            'lat' => 52.0012,
            'lng' => -0.7355,
        ]);
        $momentSvc->update((int) $moment['id'], [
            'kind' => 'roundabout',
            'label' => 'Kingston Roundabout',
            'learner_note' => 'Watch lane position',
            'learner_visible' => true,
        ]);
        $routes->shareWithLearner((int) $lesson['id']);

        $portal = new PortalAuthService();
        $invite = $portal->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $portal->activate(['token' => $query['token'], 'password' => 'learnerpass1']);

        $learn = (new LearnService())->portalHome();
        $this->assertNotEmpty($learn['for_you']);
        $this->assertSame('next_focus', $learn['for_you'][0]['reason']);
        $this->assertNotEmpty($learn['places_to_review']);

        $moments = $momentSvc->forPortalRoute(
            (int) (new LessonRouteService())->listForPortalLearner()[0]['id'],
        );
        $this->assertCount(1, $moments);
        $this->assertSame('Watch lane position', $moments[0]['learner_note']);
    }
}
