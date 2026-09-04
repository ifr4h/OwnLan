<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\CompanionFeature;
use app\services\CompanionService;
use Yii;
use yii\filters\VerbFilter;

/**
 * Broad Companion HTTP API — DEFERRED / NOT CURRENTLY EXPOSED.
 *
 * Architecture preserved for future purpose-limited access (e.g. Payment Contact).
 * See docs/17-companion-access-decision.md.
 */
class CompanionController extends BaseApiController
{
    private CompanionService $companions;

    public function init(): void
    {
        parent::init();
        $this->companions = new CompanionService();
    }

    public function beforeAction($action): bool
    {
        CompanionFeature::requireEnabled();

        return parent::beforeAction($action);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'peek-invite' => ['GET'],
                    'activate' => ['POST'],
                    'login' => ['POST'],
                    'logout' => ['POST'],
                    'me' => ['GET'],
                    'learner-home' => ['GET'],
                    'practice-note' => ['POST'],
                ],
            ],
        ];
    }

    public function actionPeekInvite(): array
    {
        return $this->companions->peekInvite((string) Yii::$app->request->get('token', ''));
    }

    public function actionActivate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->companions->activate((array) Yii::$app->request->bodyParams);
    }

    public function actionLogin(): array
    {
        return $this->companions->login((array) Yii::$app->request->bodyParams);
    }

    public function actionLogout(): array
    {
        $this->companions->logout();

        return ['status' => 'ok'];
    }

    public function actionMe(): array
    {
        return $this->companions->currentPayload();
    }

    public function actionLearnerHome(int $id): array
    {
        return $this->companions->learnerHome($id);
    }

    public function actionPracticeNote(int $id, int $sessionId): array
    {
        return $this->companions->addPracticeNote($id, $sessionId, (array) Yii::$app->request->bodyParams);
    }
}
