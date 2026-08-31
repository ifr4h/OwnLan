<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\models\Instructor;
use app\models\Membership;
use app\models\Organisation;
use app\models\User;
use app\services\AuthService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class AuthServiceTest extends Unit
{
    protected UnitTester $tester;

    protected function _before(): void
    {
        Yii::$app->db->createCommand('TRUNCATE memberships, instructors, organisations, users RESTART IDENTITY CASCADE')->execute();
        Yii::$app->user->logout();
    }

    public function testRegisterCreatesAllRecordsInOneGo(): void
    {
        $user = (new AuthService())->register([
            'name' => 'Jamie Lane',
            'email' => 'Jamie.Lane@Example.com',
            'password' => 'password123',
        ]);

        $this->assertSame('jamie.lane@example.com', $user->email);
        $this->assertTrue($user->validatePassword('password123'));
        $this->assertCount(1, Organisation::find()->all());
        $this->assertCount(1, Membership::find()->all());
        $this->assertCount(1, Instructor::find()->all());
        $this->assertFalse(Yii::$app->user->isGuest);
    }
}
