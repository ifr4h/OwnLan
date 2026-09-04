<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\LessonService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class TodayController extends BaseApiController
{
    private LessonService $lessons;

    public function init(): void
    {
        parent::init();
        $this->lessons = new LessonService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
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
        return $this->lessons->today();
    }
}
