<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\SettingsService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class SettingsController extends BaseApiController
{
    private SettingsService $settings;

    public function init(): void
    {
        parent::init();
        $this->settings = new SettingsService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'update' => ['PUT', 'PATCH'],
                    'calendar-connect' => ['POST'],
                    'calendar-regenerate' => ['POST'],
                    'calendar-revoke' => ['POST'],
                    'calendar-privacy' => ['POST'],
                    'profile' => ['GET'],
                    'profile-update' => ['PUT', 'PATCH'],
                    'profile-publish' => ['POST'],
                    'profile-unpublish' => ['POST'],
                    'profile-photo' => ['GET', 'POST'],
                    'profile-cover' => ['GET', 'POST'],
                    'profile-preview' => ['GET'],
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

    public function actionIndex(): array
    {
        return $this->settings->get();
    }

    public function actionUpdate(): array
    {
        return $this->settings->update((array) Yii::$app->request->bodyParams);
    }

    public function actionCalendarConnect(): array
    {
        return $this->settings->calendarConnect();
    }

    public function actionCalendarRegenerate(): array
    {
        return $this->settings->calendarRegenerate();
    }

    public function actionCalendarRevoke(): array
    {
        $this->settings->calendarRevoke();

        return ['ok' => true];
    }

    public function actionCalendarPrivacy(): array
    {
        return $this->settings->calendarUpdatePrivacy((array) Yii::$app->request->bodyParams);
    }

    public function actionProfile(): array
    {
        return $this->settings->profileGet();
    }

    public function actionProfileUpdate(): array
    {
        return $this->settings->profileUpdate((array) Yii::$app->request->bodyParams);
    }

    public function actionProfilePublish(): array
    {
        return $this->settings->profilePublish();
    }

    public function actionProfileUnpublish(): array
    {
        return $this->settings->profileUnpublish();
    }

    public function actionProfilePreview(): array
    {
        return $this->settings->profilePreview();
    }

    public function actionProfilePhoto(): \yii\web\Response|array
    {
        if (Yii::$app->request->isPost) {
            return $this->settings->profileUploadPhoto();
        }

        return $this->settings->profilePhotoResponse();
    }

    public function actionProfileCover(): \yii\web\Response|array
    {
        if (Yii::$app->request->isPost) {
            return $this->settings->profileUploadCover();
        }

        return $this->settings->profileCoverResponse();
    }
}
