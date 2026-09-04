<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\DataExportService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

class DataExportController extends BaseApiController
{
    private DataExportService $exports;

    public function init(): void
    {
        parent::init();
        $this->exports = new DataExportService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'download' => ['GET'],
                    'accountant-pack' => ['GET'],
                    'accountant-summary' => ['GET'],
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

    public function actionDownload(): Response
    {
        $type = (string) Yii::$app->request->get('type', 'pupils');
        $from = Yii::$app->request->get('from');
        $to = Yii::$app->request->get('to');

        $file = $this->exports->exportByType(
            $type,
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        return $this->fileResponse($file);
    }

    public function actionAccountantPack(): Response
    {
        $from = Yii::$app->request->get('from');
        $to = Yii::$app->request->get('to');
        $file = $this->exports->accountantPack(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        return $this->fileResponse($file);
    }

    public function actionAccountantSummary(): array
    {
        $from = Yii::$app->request->get('from');
        $to = Yii::$app->request->get('to');

        return $this->exports->accountantPackSummary(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );
    }

    /**
     * @param array{content: string, filename: string, mime: string} $file
     */
    private function fileResponse(array $file): Response
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $file['mime']);
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . str_replace('"', '', $file['filename']) . '"',
        );
        $response->content = $file['content'];

        return $response;
    }
}
