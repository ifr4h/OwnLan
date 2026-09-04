<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Membership;
use app\models\User;
use app\services\AuthService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class TenantIsolationTest extends Unit
{
    protected UnitTester $tester;

    protected function _before(): void
    {
        Yii::$app->db->createCommand('TRUNCATE lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE')->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
    }

    public function testUsersAreIsolatedToTheirOwnOrganisation(): void
    {
        $auth = new AuthService();

        $userA = $auth->register([
            'name' => 'Instructor A',
            'email' => 'a@example.com',
            'password' => 'password123',
        ]);
        $orgA = TenantContext::requireOrganisationId();
        $auth->logout();

        $userB = $auth->register([
            'name' => 'Instructor B',
            'email' => 'b@example.com',
            'password' => 'password123',
        ]);
        $orgB = TenantContext::requireOrganisationId();

        $this->assertNotSame($orgA, $orgB);
        $this->assertTrue(TenantContext::ownsOrganisation($orgB));
        $this->assertFalse(TenantContext::ownsOrganisation($orgA));

        $visible = TenantContext::scopeByOrganisation(Membership::find())->all();
        $this->assertCount(1, $visible);
        $this->assertSame($orgB, (int) $visible[0]->organisation_id);
        $this->assertSame((int) $userB->id, (int) $visible[0]->user_id);

        // Switch back to A — must not see B's membership via scoped query.
        $auth->logout();
        $auth->login([
            'email' => 'a@example.com',
            'password' => 'password123',
        ]);
        $this->assertSame($orgA, TenantContext::requireOrganisationId());
        $this->assertFalse(TenantContext::ownsOrganisation($orgB));

        $visibleAsA = TenantContext::scopeByOrganisation(Membership::find())->all();
        $this->assertCount(1, $visibleAsA);
        $this->assertSame($orgA, (int) $visibleAsA[0]->organisation_id);
        $this->assertSame((int) $userA->id, (int) $visibleAsA[0]->user_id);
    }

    public function testUnauthenticatedScopeIsDenied(): void
    {
        $this->expectException(\yii\web\UnauthorizedHttpException::class);
        TenantContext::requireOrganisationId();
    }
}
