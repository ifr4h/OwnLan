<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\TemporaryShareService;
use Yii;
use yii\filters\VerbFilter;

class ShareController extends BaseApiController
{
    private TemporaryShareService $shares;

    public function init(): void
    {
        parent::init();
        $this->shares = new TemporaryShareService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'peek' => ['GET'],
                    'create' => ['POST'],
                    'revoke' => ['POST'],
                ],
            ],
        ];
    }

    public function actionPeek(string $token): array
    {
        return $this->shares->peek($token);
    }

    public function actionCreate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->shares->create((array) Yii::$app->request->bodyParams);
    }

    public function actionRevoke(int $id): array
    {
        return $this->shares->revoke($id);
    }
}
