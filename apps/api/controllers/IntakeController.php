<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\IntakeService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class IntakeController extends BaseApiController
{
    private IntakeService $intake;

    public function init(): void
    {
        parent::init();
        $this->intake = new IntakeService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'create' => ['POST'],
                    'review' => ['GET'],
                    'accept' => ['POST'],
                    'waitlist' => ['POST'],
                    'revoke' => ['POST'],
                    'peek' => ['GET'],
                    'terms' => ['GET'],
                    'submit' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $public = ['peek', 'terms', 'submit'];
        if (!in_array($action->id, $public, true) && Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }

        return true;
    }

    public function actionIndex(): array
    {
        $status = Yii::$app->request->get('status');

        return [
            'items' => $this->intake->listForInstructor(is_string($status) ? $status : null),
        ];
    }

    public function actionCreate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->intake->createInvite((array) Yii::$app->request->bodyParams);
    }

    public function actionReview(int $id): array
    {
        return $this->intake->review($id);
    }

    public function actionAccept(int $id): array
    {
        return $this->intake->accept($id);
    }

    public function actionWaitlist(int $id): array
    {
        return $this->intake->addToWaitingList($id);
    }

    public function actionRevoke(int $id): array
    {
        return $this->intake->revoke($id);
    }

    public function actionPeek(): array
    {
        $token = (string) Yii::$app->request->get('token', '');

        return $this->intake->peekPublic($token);
    }

    public function actionTerms(): array
    {
        $token = (string) Yii::$app->request->get('token', '');

        return $this->intake->termsPublic($token);
    }

    public function actionSubmit(): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $token = (string) ($body['token'] ?? Yii::$app->request->get('token', ''));
        $answers = is_array($body['answers'] ?? null) ? $body['answers'] : $body;
        unset($answers['token']);

        Yii::$app->response->statusCode = 201;

        return $this->intake->submitPublic($token, $answers);
    }
}
