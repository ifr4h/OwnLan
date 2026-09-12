<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\AvailabilityService;
use app\services\BookingSuggestionService;
use app\services\LearnerImportService;
use app\services\LearnerService;
use app\services\PortalAuthService;
use app\services\PupilAttentionService;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\Learner;
use app\models\Organisation;
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
                    'status' => ['POST'],
                    'booking-suggestion' => ['GET'],
                    'availability' => ['GET'],
                    'replace-availability' => ['PUT'],
                    'portal-invite' => ['POST'],
                    'portal-status' => ['GET'],
                    'import-template' => ['GET'],
                    'import-preview' => ['POST'],
                    'import-confirm' => ['POST'],
                    'report' => ['GET'],
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
        $statusParam = Yii::$app->request->get('status', Learner::STATUS_ALL);
        $status = is_string($statusParam) ? $statusParam : Learner::STATUS_ALL;

        $items = $this->learners->listByStatus($status, $query);
        $counts = $this->learners->statusCounts();

        if ($status === Learner::STATUS_WAITING) {
            $enriched = $this->attention->enrichWaitingList($items);

            return $this->withListFilterMeta([
                'items' => $enriched['items'],
                'status' => $status,
                'counts' => $counts,
            ]);
        }

        if ($status === Learner::STATUS_ACTIVE || $status === Learner::STATUS_ALL) {
            $enriched = $this->attention->enrichActiveList($items);

            return $this->withListFilterMeta([
                'items' => $enriched['items'],
                'attention' => $enriched['attention'],
                'status' => $status,
                'counts' => $counts,
            ]);
        }

        return $this->withListFilterMeta([
            'items' => $this->attention->enrichListColumns($items),
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function actionReport(): array
    {
        $from = Yii::$app->request->get('from');
        $to = Yii::$app->request->get('to');

        return $this->learners->report(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );
    }

    /**
     * Extra list meta for conditional filters (instructor, gear).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withListFilterMeta(array $payload): array
    {
        $orgId = TenantContext::organisationId();
        if ($orgId === null) {
            $payload['multi_instructor'] = false;
            $payload['instructors'] = [];
            $payload['offers_both_transmissions'] = false;

            return $payload;
        }

        $rows = Instructor::find()
            ->select(['id', 'display_name'])
            ->andWhere(['organisation_id' => $orgId])
            ->orderBy(['display_name' => SORT_ASC])
            ->asArray()
            ->all();

        $instructors = [];
        foreach ($rows as $row) {
            $instructors[] = [
                'id' => (int) $row['id'],
                'display_name' => (string) $row['display_name'],
            ];
        }

        $profileTransmission = Organisation::find()
            ->select(['profile_transmission'])
            ->andWhere(['id' => $orgId])
            ->scalar();

        $payload['multi_instructor'] = count($instructors) > 1;
        $payload['instructors'] = $instructors;
        $payload['offers_both_transmissions'] = $profileTransmission === 'both';

        return $payload;
    }

    public function actionWaiting(): array
    {
        $counts = $this->learners->statusCounts();
        $enriched = $this->attention->enrichWaitingList($this->learners->listWaiting());

        return [
            'items' => $enriched['items'],
            'status' => Learner::STATUS_WAITING,
            'counts' => $counts,
        ];
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

    public function actionStatus(int $id): array
    {
        $body = (array) Yii::$app->request->bodyParams;
        $status = $body['status'] ?? null;
        if (!is_string($status)) {
            throw new \yii\web\BadRequestHttpException('Status is required.');
        }

        return $this->learners->setStatus($id, $status);
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
