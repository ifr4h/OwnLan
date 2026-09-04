<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Instructor;
use app\models\Membership;
use app\models\Organisation;
use app\models\User;
use app\tests\Support\FunctionalTester;
use Yii;
use yii\web\HttpException;
use yii\web\Response;

/**
 * HTTP-level auth checks via Yii application actions (no REST module).
 */
class AuthCest
{
    public function _before(FunctionalTester $I): void
    {
        Yii::$app->db->createCommand('TRUNCATE lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE')->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('session', true) && Yii::$app->session->isActive) {
            Yii::$app->session->destroy();
            Yii::$app->session->open();
        }
    }

    public function registerCreatesTenantStackAndReturnsMe(FunctionalTester $I): void
    {
        [$status, $body] = $this->jsonAction('auth/register', 'POST', [
            'name' => 'Alex Instructor',
            'email' => 'alex@example.com',
            'password' => 'password123',
        ]);

        $I->assertSame(201, $status);
        $I->assertSame('alex@example.com', $body['user']['email'] ?? null);
        $I->assertSame('Alex Instructor', $body['user']['name'] ?? null);
        $I->assertSame("Alex's driving school", $body['organisation']['name'] ?? null);
        $I->assertSame('Europe/London', $body['organisation']['timezone'] ?? null);
        $I->assertSame('owner', $body['membership']['role'] ?? null);
        $I->assertSame('Alex Instructor', $body['instructor']['display_name'] ?? null);

        $I->seeRecord(User::class, ['email' => 'alex@example.com']);
        $I->seeRecord(Organisation::class, ['name' => "Alex's driving school"]);
        $I->seeRecord(Membership::class, ['role' => Membership::ROLE_OWNER]);
        $I->seeRecord(Instructor::class, ['display_name' => 'Alex Instructor']);
    }

    public function meRequiresAuthentication(FunctionalTester $I): void
    {
        [$status] = $this->jsonAction('auth/me', 'GET');
        $I->assertSame(401, $status);
    }

    public function loginAndLogout(FunctionalTester $I): void
    {
        [$status] = $this->jsonAction('auth/register', 'POST', [
            'name' => 'Sam Solo',
            'email' => 'sam@example.com',
            'password' => 'password123',
        ]);
        $I->assertSame(201, $status);

        [$status] = $this->jsonAction('auth/logout', 'POST');
        $I->assertSame(200, $status);

        [$status] = $this->jsonAction('auth/me', 'GET');
        $I->assertSame(401, $status);

        [$status, $body] = $this->jsonAction('auth/login', 'POST', [
            'email' => 'sam@example.com',
            'password' => 'password123',
        ]);
        $I->assertSame(200, $status);
        $I->assertSame('sam@example.com', $body['user']['email'] ?? null);

        [$status, $body] = $this->jsonAction('auth/me', 'GET');
        $I->assertSame(200, $status);
        $I->assertSame('sam@example.com', $body['user']['email'] ?? null);
        $I->assertSame('owner', $body['membership']['role'] ?? null);
    }

    public function duplicateEmailIsRejected(FunctionalTester $I): void
    {
        $payload = [
            'name' => 'First User',
            'email' => 'dup@example.com',
            'password' => 'password123',
        ];
        [$status] = $this->jsonAction('auth/register', 'POST', $payload);
        $I->assertSame(201, $status);

        $this->jsonAction('auth/logout', 'POST');

        [$status] = $this->jsonAction('auth/register', 'POST', $payload);
        $I->assertSame(409, $status);
    }

    public function wrongPasswordIsRejected(FunctionalTester $I): void
    {
        $this->jsonAction('auth/register', 'POST', [
            'name' => 'Pat',
            'email' => 'pat@example.com',
            'password' => 'password123',
        ]);
        $this->jsonAction('auth/logout', 'POST');

        [$status] = $this->jsonAction('auth/login', 'POST', [
            'email' => 'pat@example.com',
            'password' => 'wrong-password',
        ]);
        $I->assertSame(401, $status);
    }

    /**
     * @return array{0:int,1:array<string,mixed>}
     */
    private function jsonAction(string $route, string $method, array $body = []): array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        Yii::$app->request->setBodyParams($body);
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->data = null;

        try {
            $result = Yii::$app->runAction($route);
            $status = (int) Yii::$app->response->statusCode;
            $data = is_array($result) ? $result : (array) Yii::$app->response->data;

            return [$status, $data];
        } catch (HttpException $e) {
            return [$e->statusCode, ['message' => $e->getMessage()]];
        }
    }
}
