<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\LessonRouteService;
use app\services\ProgressService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

/**
 * Instructor lesson route recording — explicit start/stop only.
 */
class LessonRouteController extends BaseApiController
{
    private LessonRouteService $routes;
    private ProgressService $progress;

    public function init(): void
    {
        parent::init();
        $this->progress = new ProgressService();
        $this->routes = new LessonRouteService($this->progress);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'view' => ['GET'],
                    'start' => ['POST'],
                    'stop' => ['POST'],
                    'share' => ['POST'],
                    'unshare' => ['POST'],
                    'discard' => ['POST'],
                    'skills' => ['GET'],
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

    public function actionView(int $id): array
    {
        $route = $this->routes->forInstructorLesson($id);

        return [
            'route' => $route,
            'skills_catalogue' => $this->progress->catalogue(),
        ];
    }

    public function actionStart(int $id): array
    {
        return $this->routes->start($id);
    }

    public function actionStop(int $id): array
    {
        return $this->routes->stop($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionShare(int $id): array
    {
        return $this->routes->shareWithLearner($id);
    }

    public function actionUnshare(int $id): array
    {
        return $this->routes->unshareWithLearner($id);
    }

    public function actionDiscard(int $id): array
    {
        return $this->routes->discard($id);
    }

    public function actionSkills(): array
    {
        return ['categories' => $this->progress->catalogue()];
    }
}
