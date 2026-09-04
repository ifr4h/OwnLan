<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\LearnerPortalAccount;
use app\models\Organisation;
use app\services\AuthService;
use app\services\FinanceService;
use app\services\LearnerService;
use app\services\LessonService;
use app\services\PortalAuthService;
use app\services\PortalHomeService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\TooManyRequestsHttpException;
use yii\web\UnauthorizedHttpException;

class LearnerPortalServiceTest extends Unit
{
    protected UnitTester $tester;

    private PortalAuthService $portalAuth;
    private PortalHomeService $portalHome;
    private LearnerService $learners;
    private LessonService $lessons;
    private FinanceService $finance;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, lesson_routes, lesson_skills, learner_skill_progress, password_reset_tokens, learner_portal_accounts, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        TenantContext::clear();

        $this->portalAuth = new PortalAuthService();
        $this->portalHome = new PortalHomeService();
        $this->learners = new LearnerService();
        $this->lessons = new LessonService();
        $this->finance = new FinanceService();

        (new AuthService())->register([
            'name' => 'Portal Instructor',
            'email' => 'portal-instructor@example.com',
            'password' => 'password123',
        ]);
        $org = Organisation::find()->one();
        $org->default_hourly_rate_pence = 3500;
        $org->save(false, ['default_hourly_rate_pence']);
    }

    public function testInviteActivateAndHomeShowsOnlyLearnerSafeFields(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Aisha',
            'last_name' => 'Khan',
            'mobile' => '07700905001',
            'email' => 'aisha@example.com',
            'default_pickup_address' => '12 High Street',
        ]);
        $this->learners->update((int) $pupil['id'], [
            'test_date' => '2030-10-20',
            'test_centre' => 'Leeds',
            'private_notes' => 'SECRET instructor note',
        ]);

        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-01-10 10:00',
            'duration_minutes' => 60,
            'pickup_address' => '12 High Street',
        ]);
        $this->lessons->complete((int) $lesson['id'], [
            'instructor_notes' => 'PRIVATE: clutch issues',
            'learner_summary' => 'Good work on mirrors',
            'next_focus' => 'Roundabouts',
            'settlement' => 'charge',
        ]);

        $this->finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 5,
            'price_pence' => 16000,
            'record_payment' => true,
        ]);

        $future = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-06-01 14:00',
            'duration_minutes' => 90,
        ]);

        $invite = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        $this->assertStringContainsString('/portal/join?token=', $invite['invite_path']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $token = $query['token'] ?? '';
        $this->assertNotSame('', $token);

        // Instructor session must not satisfy portal auth
        try {
            $this->portalHome->home();
            $this->fail('Instructor session should not access portal home');
        } catch (UnauthorizedHttpException) {
            // expected
        }

        $this->portalAuth->activate([
            'token' => $token,
            'password' => 'learnerpass1',
        ]);

        $home = $this->portalHome->home();
        $encoded = json_encode($home, JSON_THROW_ON_ERROR);

        $this->assertSame('Aisha', $home['learner']['first_name']);
        $this->assertNotNull($home['next_lesson']);
        $this->assertSame((int) $future['id'], $home['next_lesson']['id']);
        $this->assertSame('Good work on mirrors', $home['progress']['last_lesson_summary']);
        $this->assertSame('Roundabouts', $home['progress']['next_focus']);
        $this->assertNotNull($home['practical_test']);
        $this->assertTrue($home['package_and_balance']['has_credit']);
        $this->assertArrayHasKey('display_name', $home['instructor']);
        $this->assertArrayHasKey('business_name', $home['instructor']);

        $this->assertStringNotContainsString('SECRET', $encoded);
        $this->assertStringNotContainsString('PRIVATE', $encoded);
        $this->assertStringNotContainsString('instructor_notes', $encoded);
        $this->assertStringNotContainsString('private_notes', $encoded);
        $this->assertStringNotContainsString('needs_you', $encoded);
        $this->assertStringNotContainsString('spending', $encoded);
        $this->assertStringNotContainsString('counts_as_income', $encoded);

        foreach ($home['previous_lessons'] as $row) {
            $this->assertArrayNotHasKey('instructor_notes', $row);
        }
    }

    public function testLearnerCannotSeeOtherPupilsLessons(): void
    {
        $aisha = $this->learners->create([
            'first_name' => 'Aisha',
            'last_name' => 'One',
            'mobile' => '07700905002',
            'email' => 'aisha2@example.com',
        ]);
        $ben = $this->learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Two',
            'mobile' => '07700905003',
            'email' => 'ben@example.com',
        ]);

        $this->lessons->create([
            'learner_id' => $ben['id'],
            'starts_at_local' => '2030-07-05 09:00',
            'duration_minutes' => 60,
        ]);
        $aishaLesson = $this->lessons->create([
            'learner_id' => $aisha['id'],
            'starts_at_local' => '2030-07-06 11:00',
            'duration_minutes' => 60,
        ]);

        $invite = $this->portalAuth->inviteForLearner((int) $aisha['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $this->portalAuth->activate([
            'token' => $query['token'],
            'password' => 'learnerpass1',
        ]);

        $home = $this->portalHome->home();
        $this->assertSame((int) $aishaLesson['id'], $home['next_lesson']['id']);
        $this->assertSame('Aisha', $home['learner']['first_name']);
        $ids = array_column(array_merge(
            $home['next_lesson'] ? [$home['next_lesson']] : [],
            $home['upcoming_lessons'],
            $home['previous_lessons'],
        ), 'id');
        $this->assertContains((int) $aishaLesson['id'], $ids);
        $benLessonId = (int) Yii::$app->db->createCommand(
            'SELECT id FROM lessons WHERE learner_id = :id ORDER BY id ASC LIMIT 1',
            [':id' => $ben['id']],
        )->queryScalar();
        $this->assertNotContains($benLessonId, $ids);
    }

    public function testCrossOrganisationPortalIsolation(): void
    {
        $pupilA = $this->learners->create([
            'first_name' => 'Org',
            'last_name' => 'Alpha',
            'mobile' => '07700905004',
            'email' => 'alpha@example.com',
        ]);
        $inviteA = $this->portalAuth->inviteForLearner((int) $pupilA['id']);
        parse_str(parse_url($inviteA['invite_path'], PHP_URL_QUERY) ?: '', $queryA);
        $this->portalAuth->activate([
            'token' => $queryA['token'],
            'password' => 'learnerpass1',
        ]);
        Yii::$app->portalUser->logout();
        Yii::$app->user->logout();
        TenantContext::clear();

        (new AuthService())->register([
            'name' => 'Other Instructor',
            'email' => 'other-instructor@example.com',
            'password' => 'password123',
        ]);
        $pupilB = $this->learners->create([
            'first_name' => 'Org',
            'last_name' => 'Beta',
            'mobile' => '07700905005',
            'email' => 'beta@example.com',
        ]);
        $this->lessons->create([
            'learner_id' => $pupilB['id'],
            'starts_at_local' => '2030-08-10 10:00',
        ]);

        // Log back in as Alpha portal — must not see Beta
        $this->portalAuth->login([
            'email' => 'alpha@example.com',
            'password' => 'learnerpass1',
        ]);
        $home = $this->portalHome->home();
        $this->assertSame('Org Alpha', $home['learner']['full_name']);
        $this->assertNull($home['next_lesson']);
        $this->assertSame([], $home['upcoming_lessons']);
    }

    public function testPortalSessionCannotUseInstructorLearnerList(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Portal',
            'mobile' => '07700905006',
            'email' => 'sam-portal@example.com',
        ]);
        $invite = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $this->portalAuth->activate([
            'token' => $query['token'],
            'password' => 'learnerpass1',
        ]);

        // Portal login clears instructor session
        $this->assertTrue(Yii::$app->user->isGuest);

        try {
            TenantContext::requireOrganisationId();
            $this->fail('Portal learner must not resolve instructor tenant');
        } catch (UnauthorizedHttpException) {
            // expected
        }

        try {
            $this->learners->listActive();
            $this->fail('Portal session must not list pupils');
        } catch (UnauthorizedHttpException|ForbiddenHttpException) {
            // expected — no instructor org
        }
    }

    public function testConnectedLearnerCannotBeReInvited(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Connected',
            'last_name' => 'Learner',
            'mobile' => '07700905007',
            'email' => 'connected@example.com',
        ]);
        $invite = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $this->portalAuth->activate([
            'token' => $query['token'],
            'password' => 'learnerpass1',
        ]);
        Yii::$app->portalUser->logout();

        (new AuthService())->login([
            'email' => 'portal-instructor@example.com',
            'password' => 'password123',
        ]);

        $status = $this->portalAuth->statusForLearner((int) $pupil['id']);
        $this->assertSame('connected', $status['status']);
        $this->assertFalse($status['can_invite']);

        $this->expectException(BadRequestHttpException::class);
        $this->portalAuth->inviteForLearner((int) $pupil['id']);
    }

    public function testReInviteReplacesPreviousToken(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Re',
            'last_name' => 'Invite',
            'mobile' => '07700905008',
            'email' => 'reinvite@example.com',
        ]);
        $first = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($first['invite_path'], PHP_URL_QUERY) ?: '', $queryA);
        $firstToken = $queryA['token'] ?? '';

        sleep(1);
        Yii::$app->cache->flush();

        $second = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($second['invite_path'], PHP_URL_QUERY) ?: '', $queryB);
        $secondToken = $queryB['token'] ?? '';
        $this->assertNotSame($firstToken, $secondToken);

        $this->expectException(NotFoundHttpException::class);
        $this->portalAuth->activate([
            'token' => $firstToken,
            'password' => 'learnerpass1',
        ]);
    }

    public function testInviteTokenCannotBeUsedForAnotherLearner(): void
    {
        $aisha = $this->learners->create([
            'first_name' => 'Aisha',
            'last_name' => 'Token',
            'mobile' => '07700905009',
            'email' => 'aisha-token@example.com',
        ]);
        $ben = $this->learners->create([
            'first_name' => 'Ben',
            'last_name' => 'Token',
            'mobile' => '07700905010',
            'email' => 'ben-token@example.com',
        ]);
        $invite = $this->portalAuth->inviteForLearner((int) $aisha['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $token = $query['token'] ?? '';

        $peek = $this->portalAuth->peekInvite($token);
        $this->assertSame('valid', $peek['state']);
        $this->assertSame('Aisha', $peek['learner_first_name']);

        $this->portalAuth->activate([
            'token' => $token,
            'password' => 'learnerpass1',
        ]);
        Yii::$app->portalUser->logout();

        (new AuthService())->login([
            'email' => 'portal-instructor@example.com',
            'password' => 'password123',
        ]);

        $benStatus = $this->portalAuth->statusForLearner((int) $ben['id']);
        $this->assertSame('not_invited', $benStatus['status']);
    }

    public function testInviteRateLimiting(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Rate',
            'last_name' => 'Limit',
            'mobile' => '07700905011',
            'email' => 'rate@example.com',
        ]);
        $this->portalAuth->inviteForLearner((int) $pupil['id']);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->portalAuth->inviteForLearner((int) $pupil['id']);
    }

    public function testExpiredInvitePeekAndStatus(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Expired',
            'last_name' => 'Invite',
            'mobile' => '07700905012',
            'email' => 'expired@example.com',
        ]);
        $invite = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $token = $query['token'] ?? '';

        $account = LearnerPortalAccount::findOne(['learner_id' => (int) $pupil['id']]);
        $this->assertNotNull($account);
        $account->invite_expires_at = gmdate('Y-m-d H:i:s', time() - 3600);
        $account->save(false, ['invite_expires_at']);

        $peek = $this->portalAuth->peekInvite($token);
        $this->assertSame('expired', $peek['state']);

        $status = $this->portalAuth->statusForLearner((int) $pupil['id']);
        $this->assertSame('invite_expired', $status['status']);
    }
}
