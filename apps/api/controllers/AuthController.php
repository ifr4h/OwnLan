<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\AuthService;
use Yii;
use yii\filters\VerbFilter;

class AuthController extends BaseApiController
{
    private AuthService $authService;

    public function init(): void
    {
        parent::init();
        $this->authService = new AuthService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'register' => ['POST'],
                    'login' => ['POST'],
                    'logout' => ['POST'],
                    'me' => ['GET'],
                ],
            ],
        ];
    }

    public function actionRegister(): array
    {
        $this->authService->register((array) Yii::$app->request->bodyParams);
        Yii::$app->response->statusCode = 201;

        return $this->authService->currentUserPayload();
    }

    public function actionLogin(): array
    {
        $this->authService->login((array) Yii::$app->request->bodyParams);

        return $this->authService->currentUserPayload();
    }

    public function actionLogout(): array
    {
        $this->authService->logout();

        return ['status' => 'ok'];
    }

    public function actionMe(): array
    {
        return $this->authService->currentUserPayload();
    }
}
