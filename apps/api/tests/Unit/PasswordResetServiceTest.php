<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\models\LearnerPortalAccount;
use app\models\PasswordResetToken;
use app\models\User;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\PasswordResetService;
use app\services\PortalAuthService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\TooManyRequestsHttpException;
use yii\web\UnauthorizedHttpException;

class PasswordResetServiceTest extends Unit
{
    protected UnitTester $tester;

    private PasswordResetService $reset;
    private PortalAuthService $portalAuth;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE password_reset_tokens, learner_portal_accounts, lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        Yii::$app->cache->flush();

        $this->reset = new PasswordResetService();
        $this->portalAuth = new PortalAuthService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Reset Instructor',
            'email' => 'instructor@example.com',
            'password' => 'oldpassword1',
        ]);
    }

    public function testInstructorResetRequestReturnsGenericMessageForKnownAndUnknownEmail(): void
    {
        $known = $this->reset->requestInstructor(['email' => 'instructor@example.com']);
        $unknown = $this->reset->requestInstructor(['email' => 'nobody@example.com']);

        $this->assertSame(PasswordResetService::GENERIC_REQUEST_MESSAGE, $known['message']);
        $this->assertSame($known['message'], $unknown['message']);
        $this->assertSame('Check your email', $known['headline']);
    }

    public function testInstructorResetCreatesHashedTokenNotPlaintext(): void
    {
        $this->reset->requestInstructor(['email' => 'instructor@example.com']);

        $user = User::findByEmail('instructor@example.com');
        $this->assertNotNull($user);

        $row = PasswordResetToken::find()
            ->andWhere([
                'account_type' => PasswordResetToken::TYPE_INSTRUCTOR,
                'account_id' => $user->id,
            ])
            ->one();
        $this->assertNotNull($row);
        $this->assertSame(64, strlen($row->token_hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row->token_hash);
    }

    public function testInstructorResetConfirmChangesPasswordAndInvalidatesToken(): void
    {
        $user = User::findByEmail('instructor@example.com');
        $this->assertNotNull($user);
        $oldAuthKey = $user->auth_key;
        $plain = 'known-reset-token-instructor';

        $this->insertToken(PasswordResetToken::TYPE_INSTRUCTOR, (int) $user->id, $plain);

        $peek = $this->reset->peek($plain, PasswordResetToken::TYPE_INSTRUCTOR);
        $this->assertSame('valid', $peek['state']);

        $result = $this->reset->confirm(PasswordResetToken::TYPE_INSTRUCTOR, [
            'token' => $plain,
            'password' => 'newpassword1',
        ]);
        $this->assertSame('ok', $result['status']);

        $user->refresh();
        $this->assertTrue($user->validatePassword('newpassword1'));
        $this->assertFalse($user->validatePassword('oldpassword1'));
        $this->assertNotSame($oldAuthKey, $user->auth_key);

        $this->assertSame('used', $this->reset->peek($plain, PasswordResetToken::TYPE_INSTRUCTOR)['state']);

        $this->expectException(BadRequestHttpException::class);
        $this->reset->confirm(PasswordResetToken::TYPE_INSTRUCTOR, [
            'token' => $plain,
            'password' => 'anotherpass1',
        ]);
    }

    public function testExpiredAndInvalidInstructorResetTokens(): void
    {
        $user = User::findByEmail('instructor@example.com');
        $this->assertNotNull($user);

        $expiredPlain = 'expired-token';
        $this->insertToken(
            PasswordResetToken::TYPE_INSTRUCTOR,
            (int) $user->id,
            $expiredPlain,
            gmdate('Y-m-d H:i:s', time() - 60),
        );
        $this->assertSame('expired', $this->reset->peek($expiredPlain, PasswordResetToken::TYPE_INSTRUCTOR)['state']);

        $this->expectException(BadRequestHttpException::class);
        $this->reset->confirm(PasswordResetToken::TYPE_INSTRUCTOR, [
            'token' => $expiredPlain,
            'password' => 'newpassword1',
        ]);
    }

    public function testPortalResetOnlyForActivatedAccounts(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Learner',
            'mobile' => '07700900111',
            'email' => 'shared@example.com',
        ]);
        $invite = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $this->portalAuth->activate([
            'token' => $query['token'],
            'password' => 'learnerpass1',
        ]);
        Yii::$app->portalUser->logout();

        $response = $this->reset->requestPortal(['email' => 'shared@example.com']);
        $this->assertSame(PasswordResetService::GENERIC_REQUEST_MESSAGE, $response['message']);

        $account = LearnerPortalAccount::findByEmail('shared@example.com');
        $this->assertNotNull($account);
        $this->assertNotNull(
            PasswordResetToken::find()
                ->andWhere([
                    'account_type' => PasswordResetToken::TYPE_PORTAL,
                    'account_id' => $account->id,
                ])
                ->one(),
        );
    }

    public function testSameEmailInBothSystemsStaysSeparate(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Dual',
            'last_name' => 'Email',
            'mobile' => '07700900222',
            'email' => 'dual@example.com',
        ]);
        $invite = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $this->portalAuth->activate([
            'token' => $query['token'],
            'password' => 'learnerpass1',
        ]);
        Yii::$app->portalUser->logout();

        (new AuthService())->register([
            'name' => 'Dual Instructor',
            'email' => 'dual@example.com',
            'password' => 'instructorpass1',
        ]);
        Yii::$app->user->logout();

        $this->reset->requestInstructor(['email' => 'dual@example.com']);
        $this->reset->requestPortal(['email' => 'dual@example.com']);

        $instructor = User::findByEmail('dual@example.com');
        $portal = LearnerPortalAccount::findByEmail('dual@example.com');
        $this->assertNotNull($instructor);
        $this->assertNotNull($portal);

        $instructorToken = PasswordResetToken::find()
            ->andWhere([
                'account_type' => PasswordResetToken::TYPE_INSTRUCTOR,
                'account_id' => $instructor->id,
            ])
            ->one();
        $portalToken = PasswordResetToken::find()
            ->andWhere([
                'account_type' => PasswordResetToken::TYPE_PORTAL,
                'account_id' => $portal->id,
            ])
            ->one();
        $this->assertNotNull($instructorToken);
        $this->assertNotNull($portalToken);
        $this->assertNotSame($instructorToken->token_hash, $portalToken->token_hash);
    }

    public function testCrossEndpointTokensFailSafely(): void
    {
        $user = User::findByEmail('instructor@example.com');
        $this->assertNotNull($user);
        $instructorPlain = 'instructor-only-token';
        $this->insertToken(PasswordResetToken::TYPE_INSTRUCTOR, (int) $user->id, $instructorPlain);

        $this->assertSame('invalid', $this->reset->peek($instructorPlain, PasswordResetToken::TYPE_PORTAL)['state']);

        $this->expectException(NotFoundHttpException::class);
        $this->reset->confirm(PasswordResetToken::TYPE_PORTAL, [
            'token' => $instructorPlain,
            'password' => 'newpassword1',
        ]);
    }

    public function testPortalPasswordResetFlow(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Portal',
            'last_name' => 'Reset',
            'mobile' => '07700900333',
            'email' => 'portal-reset@example.com',
        ]);
        $invite = $this->portalAuth->inviteForLearner((int) $pupil['id']);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $this->portalAuth->activate([
            'token' => $query['token'],
            'password' => 'oldlearner1',
        ]);
        Yii::$app->portalUser->logout();

        $account = LearnerPortalAccount::findByEmail('portal-reset@example.com');
        $this->assertNotNull($account);
        $plain = 'portal-reset-token';
        $this->insertToken(PasswordResetToken::TYPE_PORTAL, (int) $account->id, $plain);

        $this->reset->confirm(PasswordResetToken::TYPE_PORTAL, [
            'token' => $plain,
            'password' => 'newlearner1',
        ]);

        $account->refresh();
        $this->assertTrue($account->validatePassword('newlearner1'));

        $this->portalAuth->login([
            'email' => 'portal-reset@example.com',
            'password' => 'newlearner1',
        ]);
        $this->assertFalse(Yii::$app->portalUser->isGuest);

        $this->expectException(UnauthorizedHttpException::class);
        $this->portalAuth->login([
            'email' => 'portal-reset@example.com',
            'password' => 'oldlearner1',
        ]);
    }

    public function testResetRateLimiting(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->reset->requestInstructor(['email' => 'instructor@example.com']);
        }

        $this->expectException(TooManyRequestsHttpException::class);
        $this->reset->requestInstructor(['email' => 'instructor@example.com']);
    }

    public function testNewInstructorResetInvalidatesPreviousUnusedToken(): void
    {
        $user = User::findByEmail('instructor@example.com');
        $this->assertNotNull($user);
        $firstPlain = 'first-reset-token';
        $this->insertToken(PasswordResetToken::TYPE_INSTRUCTOR, (int) $user->id, $firstPlain);

        $this->reset->requestInstructor(['email' => 'instructor@example.com']);

        $this->assertSame('used', $this->reset->peek($firstPlain, PasswordResetToken::TYPE_INSTRUCTOR)['state']);
    }

    private function insertToken(
        string $accountType,
        int $accountId,
        string $plain,
        ?string $expiresAt = null,
    ): void {
        $row = new PasswordResetToken();
        $row->account_type = $accountType;
        $row->account_id = $accountId;
        $row->token_hash = hash('sha256', $plain);
        $row->expires_at = $expiresAt ?? gmdate('Y-m-d H:i:s', time() + PasswordResetService::RESET_TTL_SECONDS);
        $row->created_at = gmdate('Y-m-d H:i:s');
        $this->assertTrue($row->save());
    }
}
