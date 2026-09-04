<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\ProfilePhotoStorage;
use app\services\EnquiryService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

class EnquiryController extends BaseApiController
{
    private EnquiryService $enquiries;

    public function init(): void
    {
        parent::init();
        $this->enquiries = new EnquiryService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'stats' => ['GET'],
                    'view' => ['GET'],
                    'contact' => ['POST'],
                    'accept' => ['POST'],
                    'decline' => ['POST'],
                    'convert' => ['POST'],
                    'waiting' => ['POST'],
                    'book' => ['POST'],
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
        $status = Yii::$app->request->get('status');
        $transmission = Yii::$app->request->get('transmission');

        return [
            'items' => $this->enquiries->listForInstructor(
                is_string($status) ? $status : null,
                is_string($transmission) ? $transmission : null,
            ),
        ];
    }

    public function actionStats(): array
    {
        return $this->enquiries->stats();
    }

    public function actionView(int $id): array
    {
        return $this->enquiries->view($id);
    }

    public function actionContact(int $id): array
    {
        return $this->enquiries->markContacted($id);
    }

    public function actionAccept(int $id): array
    {
        return $this->enquiries->accept($id);
    }

    public function actionDecline(int $id): array
    {
        return $this->enquiries->decline($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionConvert(int $id): array
    {
        return $this->enquiries->convert($id, waiting: false);
    }

    public function actionWaiting(int $id): array
    {
        return $this->enquiries->convert($id, waiting: true);
    }

    public function actionBook(int $id): array
    {
        return $this->enquiries->bookFirstLesson($id, (array) Yii::$app->request->bodyParams);
    }
}
