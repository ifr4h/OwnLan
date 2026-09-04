<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\BusinessFinanceService;
use app\services\MileageService;
use app\services\VehicleService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;

class BusinessController extends BaseApiController
{
    private BusinessFinanceService $business;
    private VehicleService $vehicles;
    private MileageService $mileage;

    public function init(): void
    {
        parent::init();
        $this->business = new BusinessFinanceService();
        $this->vehicles = new VehicleService();
        $this->mileage = new MileageService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'overview' => ['GET'],
                    'report' => ['GET'],
                    'export' => ['GET'],
                    'create-expense' => ['POST'],
                    'void-expense' => ['POST'],
                    'attach-receipt' => ['POST'],
                    'receipt' => ['GET'],
                    'upsert-goal' => ['POST'],
                    'delete-goal' => ['POST'],
                    'list-vehicles' => ['GET'],
                    'create-vehicle' => ['POST'],
                    'update-vehicle' => ['POST'],
                    'vehicle-detail' => ['GET'],
                    'list-mileage' => ['GET'],
                    'create-mileage' => ['POST'],
                    'delete-mileage' => ['POST'],
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

    public function actionOverview(): array
    {
        $from = Yii::$app->request->get('from');
        $to = Yii::$app->request->get('to');

        return $this->business->overview(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );
    }

    public function actionReport(): array
    {
        $from = Yii::$app->request->get('from');
        $to = Yii::$app->request->get('to');

        return $this->business->report(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );
    }

    public function actionExport(): Response
    {
        $from = Yii::$app->request->get('from');
        $to = Yii::$app->request->get('to');
        $type = Yii::$app->request->get('type', 'combined');
        $csv = $this->business->exportByType(
            is_string($type) ? $type : 'combined',
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        $fromPart = is_string($from) && $from !== '' ? $from : 'start';
        $toPart = is_string($to) && $to !== '' ? $to : 'end';
        $typePart = is_string($type) && $type !== '' ? $type : 'combined';
        $filename = 'ownlane-' . $typePart . '-' . $fromPart . '-to-' . $toPart . '.csv';

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->content = $csv;

        return $response;
    }

    public function actionCreateExpense(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->business->createExpense((array) Yii::$app->request->bodyParams);
    }

    public function actionVoidExpense(int $id): array
    {
        return $this->business->voidExpense($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionAttachReceipt(int $id): array
    {
        return $this->business->attachReceipt($id, UploadedFile::getInstanceByName('receipt'));
    }

    public function actionReceipt(int $id): Response
    {
        $receipt = $this->business->receiptContents($id);
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $receipt['mime']);
        $response->headers->set(
            'Content-Disposition',
            'inline; filename="' . str_replace('"', '', (string) $receipt['filename']) . '"',
        );
        $response->content = $receipt['content'];

        return $response;
    }

    public function actionUpsertGoal(): array
    {
        return $this->business->upsertGoal((array) Yii::$app->request->bodyParams);
    }

    public function actionDeleteGoal(): array
    {
        $this->business->deleteGoal((array) Yii::$app->request->bodyParams);

        return ['ok' => true];
    }

    public function actionListVehicles(): array
    {
        return ['vehicles' => $this->vehicles->list()];
    }

    public function actionCreateVehicle(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->vehicles->create((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdateVehicle(int $id): array
    {
        return $this->vehicles->update($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionVehicleDetail(int $id): array
    {
        $year = Yii::$app->request->get('year');

        return $this->vehicles->detail($id, is_numeric($year) ? (int) $year : null);
    }

    public function actionListMileage(): array
    {
        $from = Yii::$app->request->get('from');
        $to = Yii::$app->request->get('to');

        return [
            'entries' => $this->mileage->list(
                is_string($from) ? $from : null,
                is_string($to) ? $to : null,
            ),
        ];
    }

    public function actionCreateMileage(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->mileage->create((array) Yii::$app->request->bodyParams);
    }

    public function actionDeleteMileage(int $id): array
    {
        $this->mileage->delete($id);

        return ['ok' => true];
    }
}
