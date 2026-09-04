<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\TeachingStudioService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class TeachingController extends BaseApiController
{
    private TeachingStudioService $studio;

    public function init(): void
    {
        parent::init();
        $this->studio = new TeachingStudioService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'templates' => ['GET'],
                    'index' => ['GET'],
                    'view' => ['GET'],
                    'create' => ['POST'],
                    'update' => ['PUT', 'PATCH'],
                    'duplicate' => ['POST'],
                    'archive' => ['POST'],
                    'lesson-resources' => ['GET'],
                    'attach-lesson' => ['POST'],
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

    public function actionTemplates(): array
    {
        return ['items' => $this->studio->templates()];
    }

    public function actionIndex(): array
    {
        $category = Yii::$app->request->get('category');
        $fav = Yii::$app->request->get('favourites') === '1';

        return [
            'items' => $this->studio->listLibrary(
                is_string($category) ? $category : null,
                $fav,
            ),
        ];
    }

    public function actionView(int $id): array
    {
        return $this->studio->get($id);
    }

    public function actionCreate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->studio->create((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdate(int $id): array
    {
        return $this->studio->update($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionDuplicate(int $id): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->studio->duplicate($id);
    }

    public function actionArchive(int $id): array
    {
        return $this->studio->archive($id);
    }

    public function actionLessonResources(int $id): array
    {
        return ['items' => $this->studio->forLesson($id)];
    }

    public function actionAttachLesson(int $id): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->studio->attachToLesson($id, (array) Yii::$app->request->bodyParams);
    }
}
