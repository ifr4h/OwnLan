<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Learner;
use app\tests\Support\FunctionalTester;
use Yii;
use yii\web\HttpException;
use yii\web\Response;

class LearnerCest
{
    public function _before(FunctionalTester $I): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('session', true) && Yii::$app->session->isActive) {
            Yii::$app->session->destroy();
            Yii::$app->session->open();
        }
    }

    public function requiresAuthentication(FunctionalTester $I): void
    {
        [$status] = $this->jsonAction('learner/index', 'GET');
        $I->assertSame(401, $status);
    }

    public function crudArchiveAndSearch(FunctionalTester $I): void
    {
        [$status] = $this->jsonAction('auth/register', 'POST', [
            'name' => 'Alex Instructor',
            'email' => 'alex.pupils@example.com',
            'password' => 'password123',
        ]);
        $I->assertSame(201, $status);

        [$status, $created] = $this->jsonAction('learner/create', 'POST', [
            'first_name' => 'Chloe',
            'last_name' => 'Nguyen',
            'mobile' => '07700 900999',
            'default_pickup_address' => 'Station Road',
        ]);
        $I->assertSame(201, $status);
        $I->assertSame('Chloe Nguyen', $created['full_name'] ?? null);
        $id = (int) $created['id'];

        $I->seeRecord(Learner::class, [
            'first_name' => 'Chloe',
            'last_name' => 'Nguyen',
        ]);

        [$status, $list] = $this->jsonAction('learner/index', 'GET', [], ['q' => 'nguyen']);
        $I->assertSame(200, $status);
        $I->assertCount(1, $list['items'] ?? []);

        [$status, $view] = $this->jsonAction('learner/view', 'GET', [], ['id' => $id]);
        $I->assertSame(200, $status);
        $I->assertSame('Station Road', $view['default_pickup_address'] ?? null);

        [$status, $updated] = $this->jsonAction('learner/update', 'PUT', [
            'private_notes' => 'Nervous on roundabouts',
            'test_date' => '2026-12-01',
        ], ['id' => $id]);
        $I->assertSame(200, $status);
        $I->assertSame('Nervous on roundabouts', $updated['private_notes'] ?? null);

        [$status, $archived] = $this->jsonAction('learner/archive', 'POST', [], ['id' => $id]);
        $I->assertSame(200, $status);
        $I->assertNotNull($archived['archived_at'] ?? null);

        [$status, $listAfter] = $this->jsonAction('learner/index', 'GET');
        $I->assertSame(200, $status);
        $I->assertCount(0, $listAfter['items'] ?? []);
    }

    public function cannotAccessOtherOrganisationPupil(FunctionalTester $I): void
    {
        $this->jsonAction('auth/register', 'POST', [
            'name' => 'Instructor A',
            'email' => 'iso-a@example.com',
            'password' => 'password123',
        ]);
        [, $pupil] = $this->jsonAction('learner/create', 'POST', [
            'first_name' => 'Hidden',
            'last_name' => 'Pupil',
            'mobile' => '07700900333',
        ]);
        $this->jsonAction('auth/logout', 'POST');

        $this->jsonAction('auth/register', 'POST', [
            'name' => 'Instructor B',
            'email' => 'iso-b@example.com',
            'password' => 'password123',
        ]);

        [$status] = $this->jsonAction('learner/view', 'GET', [], ['id' => (int) $pupil['id']]);
        $I->assertSame(404, $status);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     * @return array{0:int,1:array<string,mixed>}
     */
    private function jsonAction(string $route, string $method, array $body = [], array $query = []): array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        Yii::$app->request->setQueryParams($query);
        Yii::$app->request->setBodyParams($body);
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->data = null;

        $params = [];
        if (isset($query['id'])) {
            $params['id'] = (int) $query['id'];
        }

        try {
            $result = Yii::$app->runAction($route, $params);
            $status = (int) Yii::$app->response->statusCode;
            $data = is_array($result) ? $result : (array) Yii::$app->response->data;

            return [$status, $data];
        } catch (HttpException $e) {
            return [$e->statusCode, ['message' => $e->getMessage()]];
        }
    }
}
