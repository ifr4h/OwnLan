<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\LearnerBookingAvailabilityService;
use app\services\LessonBookingRequestService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class BookingRequestController extends BaseApiController
{
    private LessonBookingRequestService $requests;
    private LearnerBookingAvailabilityService $availability;

    public function init(): void
    {
        parent::init();
        $this->requests = new LessonBookingRequestService();
        $this->availability = new LearnerBookingAvailabilityService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'accept' => ['POST'],
                    'decline' => ['POST'],
                    'suggest' => ['POST'],
                    'availability' => ['GET'],
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
        return [
            'items' => $this->requests->listPendingForInstructor(),
        ];
    }

    public function actionAccept(int $id): array
    {
        return $this->requests->accept($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionDecline(int $id): array
    {
        return $this->requests->decline($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionSuggest(int $id): array
    {
        return $this->requests->suggestAlternative($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionAvailability(int $learnerId): array
    {
        return $this->availability->forInstructorLearner($learnerId, (array) Yii::$app->request->get());
    }
}
