<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\AuthService;
use app\services\PasswordResetService;
use app\services\UnifiedAuthService;
use Yii;
use yii\filters\VerbFilter;

class AuthController extends BaseApiController
{
    private AuthService $authService;
    private UnifiedAuthService $unifiedAuth;
    private PasswordResetService $passwordReset;

    public function init(): void
    {
        parent::init();
        $this->authService = new AuthService();
        $this->unifiedAuth = new UnifiedAuthService($this->authService);
        $this->passwordReset = new PasswordResetService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'register' => ['POST'],
                    'login' => ['POST'],
                    'sign-in' => ['POST'],
                    'logout' => ['POST'],
                    'me' => ['GET'],
                    'password-reset-request' => ['POST'],
                    'password-reset-peek' => ['GET'],
                    'password-reset-confirm' => ['POST'],
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

    public function actionSignIn(): array
    {
        return $this->unifiedAuth->signIn((array) Yii::$app->request->bodyParams);
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

    public function actionPasswordResetRequest(): array
    {
        return $this->passwordReset->requestInstructor((array) Yii::$app->request->bodyParams);
    }

    public function actionPasswordResetPeek(): array
    {
        $token = (string) Yii::$app->request->get('token', '');

        return $this->passwordReset->peek($token, \app\models\PasswordResetToken::TYPE_INSTRUCTOR);
    }

    public function actionPasswordResetConfirm(): array
    {
        return $this->passwordReset->confirm(
            \app\models\PasswordResetToken::TYPE_INSTRUCTOR,
            (array) Yii::$app->request->bodyParams,
        );
    }
}
