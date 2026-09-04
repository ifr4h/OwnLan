<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\GlobalSearchService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class SearchController extends BaseApiController
{
    private GlobalSearchService $search;

    public function init(): void
    {
        parent::init();
        $this->search = new GlobalSearchService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'recent' => ['GET'],
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
        $q = Yii::$app->request->get('q', '');

        return $this->search->search(is_string($q) ? $q : '');
    }

    public function actionRecent(): array
    {
        return [
            'pupils' => $this->search->recentPupils(5),
            'quick_actions' => [
                ['id' => 'add_lesson', 'label' => 'Add lesson', 'path' => '/lessons/new'],
                ['id' => 'add_pupil', 'label' => 'Add pupil', 'path' => '/pupils/new'],
                ['id' => 'add_expense', 'label' => 'Add expense', 'path' => '/accounts/expenses'],
            ],
        ];
    }
}
