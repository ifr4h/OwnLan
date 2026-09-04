<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\PortalContext;
use app\components\TenantContext;
use app\models\CompanionAccount;
use app\models\LearnerCompanion;
use app\models\LearnerSkillProgress;
use app\models\Organisation;
use app\models\PrivatePracticeSession;
use app\services\AuthService;
use app\services\CompanionService;
use app\services\LearnerService;
use app\services\LearnService;
use app\services\LessonService;
use app\services\PortalAuthService;
use app\services\PlaybackService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\ForbiddenHttpException;

class CompanionServiceTest extends Unit
{
    protected UnitTester $tester;

    private CompanionService $companions;
    private int $learnerId;
    private int $orgId;

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
            'name' => 'Companion Instructor',
            'email' => 'companion-inst@example.com',
            'password' => 'password123',
        ]);
        $org = Organisation::find()->one();
        $this->orgId = (int) $org->id;

        $pupil = (new LearnerService())->create([
            'first_name' => 'Sarah',
            'last_name' => 'Learner',
            'mobile' => '07700908001',
            'email' => 'sarah.learner@example.com',
        ]);
        $this->learnerId = (int) $pupil['id'];
        $learner = \app\models\Learner::findOne($this->learnerId);
        $learner->next_focus = 'Independent roundabouts';
        $learner->save(false, ['next_focus']);

        (new LessonService())->create([
            'learner_id' => $this->learnerId,
            'starts_at_local' => '2026-05-01 10:00',
            'duration_minutes' => 60,
        ]);

        $invite = (new PortalAuthService())->inviteForLearner($this->learnerId);
        parse_str(parse_url((string) $invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        (new PortalAuthService())->activate([
            'token' => $query['token'],
            'password' => 'learnerpass1',
        ]);

        $this->companions = new CompanionService();
    }

    public function testPaymentCompanionCannotSeeProgressOrPractice(): void
    {
        $created = $this->inviteAsLearner([
            'name' => 'Ahmed',
            'email' => 'ahmed.pay@example.com',
            'permissions' => [
                'lessons' => true,
                'money' => true,
                'progress' => false,
                'practice' => false,
                'learn' => false,
            ],
        ]);

        $this->activateCompanion($created['invite_path'], 'companionpass1');
        $home = $this->companions->learnerHome($this->learnerId);

        $this->assertArrayHasKey('next_lesson', $home);
        $this->assertArrayHasKey('money', $home);
        $this->assertArrayNotHasKey('next_focus', $home);
        $this->assertArrayNotHasKey('practice_focus', $home);
        $this->assertArrayNotHasKey('recent_practice', $home);
    }

    public function testPracticeCompanionSeesFocusNotMoney(): void
    {
        $created = $this->inviteAsLearner([
            'name' => 'Fatima',
            'email' => 'fatima.practice@example.com',
            'permissions' => [
                'practice' => true,
                'learn' => true,
                'lessons' => false,
                'money' => false,
                'progress' => false,
            ],
        ]);
        $this->activateCompanion($created['invite_path'], 'companionpass1');
        $home = $this->companions->learnerHome($this->learnerId);

        $this->assertSame('Independent roundabouts', $home['practice_focus']);
        $this->assertArrayNotHasKey('money', $home);
        $this->assertArrayNotHasKey('next_lesson', $home);
    }

    public function testRevokedCompanionLosesAccess(): void
    {
        $created = $this->inviteAsLearner([
            'name' => 'Revoke Me',
            'email' => 'revoke.me@example.com',
            'permissions' => ['lessons' => true],
        ]);
        $linkId = (int) $created['companion']['id'];
        $this->activateCompanion($created['invite_path'], 'companionpass1');

        Yii::$app->companionUser->logout();
        $this->loginPortal();
        $this->companions->revoke($linkId);

        $this->activateOrLoginCompanion('revoke.me@example.com', 'companionpass1');
        $this->expectException(ForbiddenHttpException::class);
        $this->companions->learnerHome($this->learnerId);
    }

    public function testCompanionCannotAccessOtherLearner(): void
    {
        // Create other learner while instructor session is still active.
        Yii::$app->companionUser->logout();
        Yii::$app->portalUser->logout();
        // Re-establish instructor from AuthService login via existing user session after register in _before
        // TenantContext needs instructor user — re-login instructor.
        (new AuthService())->login([
            'email' => 'companion-inst@example.com',
            'password' => 'password123',
        ]);
        $other = (new LearnerService())->create([
            'first_name' => 'Other',
            'last_name' => 'Pupil',
            'mobile' => '07700908002',
            'email' => 'other.pupil@example.com',
        ]);

        $created = $this->inviteAsLearner([
            'name' => 'Scoped',
            'email' => 'scoped@example.com',
            'permissions' => ['lessons' => true],
        ]);
        $this->activateCompanion($created['invite_path'], 'companionpass1');

        $this->expectException(ForbiddenHttpException::class);
        $this->companions->learnerHome((int) $other['id']);
    }

    public function testLearningActivityDoesNotChangeInstructorProgress(): void
    {
        $this->loginPortal();
        $before = (int) LearnerSkillProgress::find()
            ->andWhere(['learner_id' => $this->learnerId, 'organisation_id' => $this->orgId])
            ->count();

        (new PlaybackService())->recordActivity([
            'content_slug' => 'interactive-third-exit',
            'activity_type' => 'scenario_completed',
            'result' => ['ok' => true],
        ]);

        $after = (int) LearnerSkillProgress::find()
            ->andWhere(['learner_id' => $this->learnerId, 'organisation_id' => $this->orgId])
            ->count();
        $this->assertSame($before, $after);
    }

    public function testPracticeNoteIsCompanionProvenance(): void
    {
        $this->loginPortal();
        $session = (new LearnService())->logPrivatePractice([
            'duration_minutes' => 40,
            'skill_codes' => ['roundabouts'],
            'feeling' => 'okay',
            'note' => 'Learner reflection',
        ]);

        $created = $this->inviteAsLearner([
            'name' => 'Note Taker',
            'email' => 'notes@example.com',
            'permissions' => ['practice' => true],
        ]);
        $this->activateCompanion($created['invite_path'], 'companionpass1');
        $result = $this->companions->addPracticeNote($this->learnerId, (int) $session['id'], [
            'companion_note' => 'Smoother on approach',
        ]);

        $this->assertSame('companion', $result['provenance']);
        $row = PrivatePracticeSession::findOne((int) $session['id']);
        $this->assertSame('Learner reflection', $row->note);
        $this->assertSame('Smoother on approach', $row->companion_note);
        $this->assertSame(0, (int) LearnerSkillProgress::find()->andWhere(['learner_id' => $this->learnerId])->count());
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function inviteAsLearner(array $data): array
    {
        $this->loginPortal();

        return $this->companions->inviteFromLearner($data);
    }

    private function loginPortal(): void
    {
        if (Yii::$app->has('companionUser')) {
            Yii::$app->companionUser->logout();
        }
        (new PortalAuthService())->login([
            'email' => 'sarah.learner@example.com',
            'password' => 'learnerpass1',
        ]);
        PortalContext::requireAccount();
    }

    private function activateCompanion(?string $invitePath, string $password): void
    {
        Yii::$app->portalUser->logout();
        $token = '';
        if ($invitePath && preg_match('/token=([^&]+)/', $invitePath, $m)) {
            $token = urldecode($m[1]);
        }
        $this->companions->activate([
            'token' => $token,
            'password' => $password,
        ]);
    }

    private function activateOrLoginCompanion(string $email, string $password): void
    {
        Yii::$app->portalUser->logout();
        $account = CompanionAccount::findByEmail($email);
        if ($account !== null && $account->isActivated) {
            $this->companions->login(['email' => $email, 'password' => $password]);
        }
    }
}
