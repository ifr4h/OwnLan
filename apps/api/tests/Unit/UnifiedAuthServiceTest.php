<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\CompanionAccount;
use app\models\LearnerCompanion;
use app\models\Organisation;
use app\services\AuthService;
use app\services\CompanionService;
use app\services\LearnerService;
use app\services\PortalAuthService;
use app\services\UnifiedAuthService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class UnifiedAuthServiceTest extends Unit
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
            'name' => 'Unified Instructor',
            'email' => 'unified-inst@example.com',
            'password' => 'password123',
        ]);
    }

    public function testRoutesInstructorToToday(): void
    {
        $result = (new UnifiedAuthService())->signIn([
            'email' => 'unified-inst@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame('instructor', $result['account_type']);
        $this->assertSame('/today', $result['redirect']);
        $this->assertNotEmpty($result['payload']['user']['email']);
    }

    public function testRoutesLearnerToPortal(): void
    {
        $pupil = (new LearnerService())->create([
            'first_name' => 'Learner',
            'last_name' => 'Unified',
            'mobile' => '07700909001',
            'email' => 'learner.unified@example.com',
        ]);
        $portal = new PortalAuthService();
        $invite = $portal->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url((string) $invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $portal->activate(['token' => $query['token'], 'password' => 'learnerpass1']);

        Yii::$app->portalUser->logout();
        Yii::$app->user->logout();

        $result = (new UnifiedAuthService())->signIn([
            'email' => 'learner.unified@example.com',
            'password' => 'learnerpass1',
        ]);

        $this->assertSame('learner', $result['account_type']);
        $this->assertSame('/portal', $result['redirect']);
    }

    public function testDoesNotRouteCompanionViaUnifiedSignIn(): void
    {
        $org = Organisation::find()->one();
        $pupil = (new LearnerService())->create([
            'first_name' => 'Pupil',
            'last_name' => 'Unified',
            'mobile' => '07700909002',
            'email' => 'pupil.unified@example.com',
        ]);

        $now = gmdate('Y-m-d H:i:s');
        $companion = new CompanionAccount();
        $companion->email = 'companion.unified@example.com';
        $companion->name = 'Helper';
        $companion->setPassword('companionpass1');
        $companion->generateAuthKey();
        $companion->activated_at = $now;
        $companion->created_at = $now;
        $companion->updated_at = $now;
        $companion->save(false);

        $link = new LearnerCompanion();
        $link->organisation_id = (int) $org->id;
        $link->learner_id = (int) $pupil['id'];
        $link->companion_account_id = (int) $companion->id;
        $link->display_name = 'Helper';
        $link->permissions_json = json_encode(['lessons' => true], JSON_THROW_ON_ERROR);
        $link->invited_by = 'learner';
        $link->created_at = $now;
        $link->updated_at = $now;
        $link->save(false);

        $this->expectException(\yii\web\UnauthorizedHttpException::class);

        (new UnifiedAuthService())->signIn([
            'email' => 'companion.unified@example.com',
            'password' => 'companionpass1',
        ]);
    }
}
