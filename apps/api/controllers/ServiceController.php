<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\PackageOfferingService;
use app\services\ServiceCatalogueService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class ServiceController extends BaseApiController
{
    private ServiceCatalogueService $catalogue;
    private PackageOfferingService $packages;

    public function init(): void
    {
        parent::init();
        $this->catalogue = new ServiceCatalogueService();
        $this->packages = new PackageOfferingService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'create' => ['POST'],
                    'view' => ['GET'],
                    'update' => ['PUT', 'PATCH'],
                    'price-change' => ['POST'],
                    'create-rule' => ['POST'],
                    'update-rule' => ['PUT', 'PATCH'],
                    'create-pupil-rate' => ['POST'],
                    'update-pupil-rate' => ['PUT', 'PATCH'],
                    'create-package' => ['POST'],
                    'update-package' => ['PUT', 'PATCH'],
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
        return $this->catalogue->home();
    }

    public function actionCreate(): array
    {
        return $this->catalogue->createService((array) Yii::$app->request->bodyParams);
    }

    public function actionView(int $id): array
    {
        return $this->catalogue->viewService($id);
    }

    public function actionUpdate(int $id): array
    {
        return $this->catalogue->updateService($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionPriceChange(int $id): array
    {
        return $this->catalogue->schedulePriceChange($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionCreateRule(): array
    {
        return $this->catalogue->createPricingRule((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdateRule(int $id): array
    {
        return $this->catalogue->updatePricingRule($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionCreatePupilRate(): array
    {
        return $this->catalogue->createPupilRate((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdatePupilRate(int $id): array
    {
        return $this->catalogue->updatePupilRate($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionCreatePackage(): array
    {
        return $this->packages->create((array) Yii::$app->request->bodyParams);
    }

    public function actionUpdatePackage(int $id): array
    {
        return $this->packages->update($id, (array) Yii::$app->request->bodyParams);
    }
}
