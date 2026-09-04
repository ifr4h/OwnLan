<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\FinanceService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class FinanceController extends BaseApiController
{
    private FinanceService $finance;

    public function init(): void
    {
        parent::init();
        $this->finance = new FinanceService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'panel' => ['GET'],
                    'create-package' => ['POST'],
                    'record-payment' => ['POST'],
                    'void-payment' => ['POST'],
                    'void-package' => ['POST'],
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

    public function actionPanel(int $id): array
    {
        return $this->finance->panelForLearner($id);
    }

    public function actionCreatePackage(int $id): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->finance->createPackage($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionRecordPayment(int $id): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->finance->recordPayment($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionVoidPayment(int $id): array
    {
        return $this->finance->voidPayment($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionVoidPackage(int $id): array
    {
        return $this->finance->voidPackage($id, (array) Yii::$app->request->bodyParams);
    }
}
