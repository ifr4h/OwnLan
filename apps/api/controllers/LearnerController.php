<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\AvailabilityService;
use app\services\BookingSuggestionService;
use app\services\LearnerImportService;
use app\services\LearnerService;
use app\services\PortalAuthService;
use app\services\PupilAttentionService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;

class LearnerController extends BaseApiController
{
    private LearnerService $learners;
    private BookingSuggestionService $bookingSuggestions;
    private AvailabilityService $availability;
    private PortalAuthService $portalAuth;
    private LearnerImportService $import;
    private PupilAttentionService $attention;

    public function init(): void
    {
        parent::init();
        $this->learners = new LearnerService();
        $this->bookingSuggestions = new BookingSuggestionService();
        $this->availability = new AvailabilityService();
        $this->portalAuth = new PortalAuthService();
        $this->import = new LearnerImportService();
        $this->attention = new PupilAttentionService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'waiting' => ['GET'],
                    'view' => ['GET'],
                    'create' => ['POST'],
                    'update' => ['PUT', 'PATCH'],
                    'archive' => ['POST'],
                    'booking-suggestion' => ['GET'],
                    'availability' => ['GET'],
                    'replace-availability' => ['PUT'],
                    'portal-invite' => ['POST'],
                    'portal-status' => ['GET'],
                    'import-template' => ['GET'],
                    'import-preview' => ['POST'],
                    'import-confirm' => ['POST'],
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
        $q = Yii::$app->request->get('q');
        $query = is_string($q) ? $q : null;
        $items = $this->learners->listActive($query);

        if ($query !== null && trim($query) !== '') {
            return ['items' => $items];
        }

        return $this->attention->enrichActiveList($items);
    }

    public function actionWaiting(): array
    {
        return $this->attention->enrichWaitingList($this->learners->listWaiting());
    }

    public function actionView(int $id): array
    {
        return $this->learners->get($id);
    }

    public function actionCreate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->learners->create((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdate(int $id): array
    {
        return $this->learners->update($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionArchive(int $id): array
    {
        return $this->learners->archive($id);
    }

    public function actionBookingSuggestion(int $id): array
    {
        return $this->bookingSuggestions->suggestForLearner($id);
    }

    public function actionAvailability(int $id): array
    {
        return [
            'items' => $this->availability->listForLearner($id),
        ];
    }

    public function actionReplaceAvailability(int $id): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $windows = $body['items'] ?? [];
        if (!is_array($windows)) {
            $windows = [];
        }

        return [
            'items' => $this->availability->replaceForLearner($id, $windows),
        ];
    }

    public function actionPortalInvite(int $id): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->portalAuth->inviteForLearner($id);
    }

    public function actionPortalStatus(int $id): array
    {
        return $this->portalAuth->statusForLearner($id);
    }

    public function actionImportTemplate(): Response
    {
        $csv = $this->import->templateCsv();
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="ownlane-pupils-example.csv"');
        $response->content = $csv;

        return $response;
    }

    public function actionImportPreview(): array
    {
        return $this->import->previewFromUpload(UploadedFile::getInstanceByName('file'));
    }

    public function actionImportConfirm(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->import->confirm((array) Yii::$app->request->bodyParams);
    }
}
