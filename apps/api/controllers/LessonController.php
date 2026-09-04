<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\LessonService;
use app\services\RecurringLessonService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class LessonController extends BaseApiController
{
    private LessonService $lessons;
    private RecurringLessonService $recurring;

    public function init(): void
    {
        parent::init();
        $this->lessons = new LessonService();
        $this->recurring = new RecurringLessonService($this->lessons);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'diary' => ['GET'],
                    'view' => ['GET'],
                    'create' => ['POST'],
                    'update' => ['PUT', 'PATCH'],
                    'cancel' => ['POST'],
                    'complete' => ['POST'],
                    'no-show' => ['POST'],
                    'recurring-preview' => ['POST'],
                    'recurring-create' => ['POST'],
                    'travel-check' => ['POST'],
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
        $learnerId = Yii::$app->request->get('learner_id');
        if ($learnerId !== null && $learnerId !== '') {
            return [
                'items' => $this->lessons->listForLearner((int) $learnerId),
            ];
        }

        return [
            'items' => $this->lessons->listUpcoming(),
        ];
    }

    public function actionDiary(): array
    {
        $view = (string) (Yii::$app->request->get('view') ?: 'day');
        $date = Yii::$app->request->get('date');

        return $this->lessons->diary(
            $view,
            is_string($date) ? $date : null,
        );
    }

    public function actionTravelCheck(): array
    {
        return $this->lessons->travelCheck((array) Yii::$app->request->bodyParams);
    }

    public function actionView(int $id): array
    {
        return $this->lessons->get($id);
    }

    public function actionCreate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->lessons->create((array) Yii::$app->request->bodyParams);
    }

    public function actionRecurringPreview(): array
    {
        return $this->recurring->preview((array) Yii::$app->request->bodyParams);
    }

    public function actionRecurringCreate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->recurring->create((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdate(int $id): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $scope = (string) ($body['scope'] ?? Yii::$app->request->get('scope') ?? 'this');
        unset($body['scope']);

        return $this->recurring->updateOccurrence($id, $body, $scope);
    }

    public function actionCancel(int $id): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $scope = (string) ($body['scope'] ?? Yii::$app->request->get('scope') ?? 'this');
        unset($body['scope']);

        return $this->recurring->cancelOccurrence($id, $scope, $body);
    }

    public function actionComplete(int $id): array
    {
        return $this->lessons->complete($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionNoShow(int $id): array
    {
        return $this->lessons->markNoShow($id, (array) Yii::$app->request->bodyParams);
    }
}
