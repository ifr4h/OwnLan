<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\DiaryBlockService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class DiaryBlockController extends BaseApiController
{
    private DiaryBlockService $blocks;

    public function init(): void
    {
        parent::init();
        $this->blocks = new DiaryBlockService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['POST'],
                    'update' => ['PUT', 'PATCH'],
                    'delete' => ['DELETE', 'POST'],
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

    public function actionCreate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->blocks->create((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdate(int $id): array
    {
        return $this->blocks->update($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionDelete(int $id): array
    {
        return $this->blocks->delete($id);
    }
}
