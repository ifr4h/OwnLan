<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\LearnerLocationService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class LearnerLocationController extends BaseApiController
{
    private LearnerLocationService $locations;

    public function init(): void
    {
        parent::init();
        $this->locations = new LearnerLocationService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'create' => ['POST'],
                    'update' => ['PUT', 'PATCH'],
                    'delete' => ['DELETE', 'POST'],
                    'set-default' => ['POST'],
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

    public function actionIndex(int $id): array
    {
        return ['items' => $this->locations->listForLearner($id)];
    }

    public function actionCreate(int $id): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->locations->create($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionUpdate(int $learnerId, int $id): array
    {
        return $this->locations->update($learnerId, $id, (array) Yii::$app->request->bodyParams);
    }

    public function actionDelete(int $learnerId, int $id): array
    {
        return $this->locations->delete($learnerId, $id);
    }

    public function actionSetDefault(int $learnerId, int $id): array
    {
        return $this->locations->setDefault($learnerId, $id);
    }
}
