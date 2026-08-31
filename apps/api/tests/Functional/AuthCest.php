<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Instructor;
use app\models\Membership;
use app\models\Organisation;
use app\models\User;
use app\tests\Support\FunctionalTester;
use Yii;

class AuthCest
{
    public function _before(FunctionalTester $I): void
    {
        Yii::$app->db->createCommand('TRUNCATE memberships, instructors, organisations, users RESTART IDENTITY CASCADE')->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('session', true)) {
            Yii::$app->session->destroy();
        }
    }

    public function registerCreatesTenantStackAndReturnsMe(FunctionalTester $I): void
    {
        $I->sendPOST('/auth/register', [
            'name' => 'Alex Instructor',
            'email' => 'alex@example.com',
            'password' => 'password123',
        ]);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson([
            'user' => [
                'email' => 'alex@example.com',
                'name' => 'Alex Instructor',
            ],
            'organisation' => [
                'name' => "Alex's driving school",
                'timezone' => 'Europe/London',
            ],
            'membership' => [
                'role' => 'owner',
            ],
            'instructor' => [
                'display_name' => 'Alex Instructor',
            ],
        ]);

        $I->seeRecord(User::class, ['email' => 'alex@example.com']);
        $I->seeRecord(Organisation::class, ['name' => "Alex's driving school"]);
        $I->seeRecord(Membership::class, ['role' => Membership::ROLE_OWNER]);
        $I->seeRecord(Instructor::class, ['display_name' => 'Alex Instructor']);
    }

    public function meRequiresAuthentication(FunctionalTester $I): void
    {
        $I->sendGET('/auth/me');
        $I->seeResponseCodeIs(401);
    }

    public function loginAndLogout(FunctionalTester $I): void
    {
        $I->sendPOST('/auth/register', [
            'name' => 'Sam Solo',
            'email' => 'sam@example.com',
            'password' => 'password123',
        ]);
        $I->seeResponseCodeIs(201);

        $I->sendPOST('/auth/logout');
        $I->seeResponseCodeIs(200);

        $I->sendGET('/auth/me');
        $I->seeResponseCodeIs(401);

        $I->sendPOST('/auth/login', [
            'email' => 'sam@example.com',
            'password' => 'password123',
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'user' => ['email' => 'sam@example.com'],
        ]);

        $I->sendGET('/auth/me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'user' => ['email' => 'sam@example.com'],
            'membership' => ['role' => 'owner'],
        ]);
    }

    public function duplicateEmailIsRejected(FunctionalTester $I): void
    {
        $payload = [
            'name' => 'First User',
            'email' => 'dup@example.com',
            'password' => 'password123',
        ];
        $I->sendPOST('/auth/register', $payload);
        $I->seeResponseCodeIs(201);

        $I->sendPOST('/auth/logout');

        $I->sendPOST('/auth/register', $payload);
        $I->seeResponseCodeIs(409);
    }

    public function wrongPasswordIsRejected(FunctionalTester $I): void
    {
        $I->sendPOST('/auth/register', [
            'name' => 'Pat',
            'email' => 'pat@example.com',
            'password' => 'password123',
        ]);
        $I->sendPOST('/auth/logout');

        $I->sendPOST('/auth/login', [
            'email' => 'pat@example.com',
            'password' => 'wrong-password',
        ]);
        $I->seeResponseCodeIs(401);
    }
}
