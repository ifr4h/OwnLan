<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\PracticalTestService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class PracticalTestController extends BaseApiController
{
    private PracticalTestService $tests;

    public function init(): void
    {
        parent::init();
        $this->tests = new PracticalTestService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'catalogue' => ['GET'],
                    'index' => ['GET'],
                    'stats' => ['GET'],
                    'view' => ['GET'],
                    'create' => ['POST'],
                    'update' => ['PUT', 'PATCH'],
                    'delete' => ['DELETE', 'POST'],
                    'learner-list' => ['GET'],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }

        return true;
    }

    public function actionCatalogue(): array
    {
        return $this->tests->catalogue();
    }

    public function actionIndex(): array
    {
        $params = Yii::$app->request->queryParams;

        return $this->tests->list(
            isset($params['from']) ? (string) $params['from'] : null,
            isset($params['to']) ? (string) $params['to'] : null,
            isset($params['learner_id']) ? (int) $params['learner_id'] : null,
            isset($params['limit']) ? (int) $params['limit'] : 100,
        );
    }

    public function actionStats(): array
    {
        $params = Yii::$app->request->queryParams;

        return $this->tests->stats(
            isset($params['from']) ? (string) $params['from'] : null,
            isset($params['to']) ? (string) $params['to'] : null,
        );
    }

    public function actionView(int $id): array
    {
        return $this->tests->view($id);
    }

    public function actionCreate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->tests->create((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdate(int $id): array
    {
        return $this->tests->update($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionDelete(int $id): array
    {
        return $this->tests->delete($id);
    }

    public function actionLearnerList(int $id): array
    {
        return $this->tests->listForLearner($id);
    }
}
