<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Lesson;
use app\tests\Support\FunctionalTester;
use Yii;
use yii\web\HttpException;
use yii\web\Response;

class LessonCest
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
        [$status] = $this->jsonAction('lesson/index', 'GET');
        $I->assertSame(401, $status);
    }

    public function createEditCancelAndList(FunctionalTester $I): void
    {
        $this->jsonAction('auth/register', 'POST', [
            'name' => 'Sam Solo',
            'email' => 'sam.lessons@example.com',
            'password' => 'password123',
        ]);

        [, $pupil] = $this->jsonAction('learner/create', 'POST', [
            'first_name' => 'Chloe',
            'last_name' => 'Nguyen',
            'mobile' => '07700900999',
            'default_pickup_address' => 'Station Road',
        ]);

        [$status, $lesson] = $this->jsonAction('lesson/create', 'POST', [
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-11-20 14:30',
            'duration_minutes' => 120,
        ]);
        $I->assertSame(201, $status);
        $I->assertSame('Station Road', $lesson['pickup_address'] ?? null);
        $I->assertSame(120, $lesson['duration_minutes'] ?? null);
        $id = (int) $lesson['id'];

        $I->seeRecord(Lesson::class, [
            'id' => $id,
            'status' => Lesson::STATUS_SCHEDULED,
        ]);

        [$status, $updated] = $this->jsonAction('lesson/update', 'PUT', [
            'pickup_address' => 'College car park',
            'duration_minutes' => 60,
        ], ['id' => $id]);
        $I->assertSame(200, $status);
        $I->assertSame('College car park', $updated['pickup_address'] ?? null);

        [$status, $list] = $this->jsonAction('lesson/index', 'GET');
        $I->assertSame(200, $status);
        $I->assertGreaterThanOrEqual(1, count($list['items'] ?? []));

        [$status, $history] = $this->jsonAction('lesson/index', 'GET', [], ['learner_id' => $pupil['id']]);
        $I->assertSame(200, $status);
        $I->assertCount(1, $history['items'] ?? []);

        [$status, $cancelled] = $this->jsonAction('lesson/cancel', 'POST', [], ['id' => $id]);
        $I->assertSame(200, $status);
        $I->assertSame(Lesson::STATUS_CANCELLED, $cancelled['status'] ?? null);
        $I->assertSame(1, (int) Lesson::find()->count());
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
