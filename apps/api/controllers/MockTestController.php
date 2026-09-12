<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\LearnerSelfAssessmentService;
use app\services\MockTestService;
use app\services\ProgressService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class MockTestController extends BaseApiController
{
    private MockTestService $mocks;
    private LearnerSelfAssessmentService $selfAssessment;

    public function init(): void
    {
        parent::init();
        $this->mocks = new MockTestService();
        $this->selfAssessment = new LearnerSelfAssessmentService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'catalogue' => ['GET'],
                    'view' => ['GET'],
                    'start-lesson' => ['POST'],
                    'start-learner' => ['POST'],
                    'record-fault' => ['POST'],
                    'undo-fault' => ['POST'],
                    'finish' => ['POST'],
                    'abandon' => ['POST'],
                    'update' => ['PATCH', 'PUT'],
                    'update-fault' => ['PATCH', 'PUT'],
                    'apply-next-focus' => ['POST'],
                    'learner-list' => ['GET'],
                    'skill-detail' => ['GET'],
                    'learner-progress' => ['GET'],
                    'record-skill-rating' => ['POST'],
                    'upsert-progress-note' => ['POST'],
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

    public function actionCatalogue(): array
    {
        return $this->mocks->catalogue();
    }

    public function actionView(int $id): array
    {
        return $this->mocks->view($id);
    }

    public function actionStartLesson(int $id): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $clientSessionId = isset($body['client_session_id']) ? (string) $body['client_session_id'] : null;
        Yii::$app->response->statusCode = 201;

        return $this->mocks->startForLesson($id, $clientSessionId);
    }

    public function actionStartLearner(int $id): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $clientSessionId = isset($body['client_session_id']) ? (string) $body['client_session_id'] : null;
        Yii::$app->response->statusCode = 201;

        return $this->mocks->startForLearner($id, $clientSessionId);
    }

    public function actionRecordFault(int $id): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->mocks->recordFault($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionUndoFault(int $id, int $faultId): array
    {
        return $this->mocks->undoFault($id, $faultId);
    }

    public function actionFinish(int $id): array
    {
        return $this->mocks->finish($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionAbandon(int $id): array
    {
        return $this->mocks->abandon($id);
    }

    public function actionUpdate(int $id): array
    {
        return $this->mocks->updateCompleted($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionUpdateFault(int $id, int $faultId): array
    {
        return $this->mocks->updateFaultNote($id, $faultId, (array) Yii::$app->request->bodyParams);
    }

    public function actionApplyNextFocus(int $id): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $focus = (string) ($body['next_focus'] ?? '');

        return $this->mocks->applyNextFocus($id, $focus);
    }

    public function actionLearnerList(int $id): array
    {
        return $this->mocks->listForLearner($id);
    }

    public function actionLearnerProgress(int $id): array
    {
        return (new ProgressService())->instructorProgress($id);
    }

    public function actionRecordSkillRating(int $id, int $skillId): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $rating = (string) ($body['rating'] ?? '');

        return (new ProgressService())->recordInstructorRating($id, $skillId, $rating);
    }

    public function actionUpsertProgressNote(int $id): array
    {
        return (new ProgressService())->upsertProgressNote($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionSkillDetail(int $learnerId, string $code): array
    {
        return $this->selfAssessment->instructorSkillDetail($learnerId, $code, $this->mocks);
    }
}
