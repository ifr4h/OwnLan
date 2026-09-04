<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\LearnService;
use app\services\RouteMomentService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class LearningController extends BaseApiController
{
    private LearnService $learn;
    private RouteMomentService $moments;

    public function init(): void
    {
        parent::init();
        $this->learn = new LearnService();
        $this->moments = new RouteMomentService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'mark-moment' => ['POST'],
                    'update-moment' => ['PATCH', 'PUT'],
                    'lesson-moments' => ['GET'],
                    'private-practice-since' => ['GET'],
                    'admin-contents' => ['GET'],
                    'admin-save-content' => ['POST'],
                    'admin-update-content' => ['PUT', 'PATCH'],
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

    public function actionMarkMoment(int $id): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->moments->mark($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionUpdateMoment(int $id): array
    {
        return $this->moments->update($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionLessonMoments(int $id): array
    {
        return ['items' => $this->moments->forLesson($id)];
    }

    public function actionPrivatePracticeSince(int $id): array
    {
        return [
            'since_last_lesson' => $this->learn->instructorSinceLastLesson($id),
        ];
    }

    public function actionAdminContents(): array
    {
        $status = Yii::$app->request->get('status');

        return [
            'items' => $this->learn->adminList(is_string($status) ? $status : null),
        ];
    }

    public function actionAdminSaveContent(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->learn->adminSave(null, (array) Yii::$app->request->bodyParams);
    }

    public function actionAdminUpdateContent(int $id): array
    {
        return $this->learn->adminSave($id, (array) Yii::$app->request->bodyParams);
    }
}
