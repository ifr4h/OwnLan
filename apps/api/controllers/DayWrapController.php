<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\DayWrapService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class DayWrapController extends BaseApiController
{
    private DayWrapService $dayWrap;

    public function init(): void
    {
        parent::init();
        $this->dayWrap = new DayWrapService();
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
        return $this->dayWrap->forDay();
    }
}
