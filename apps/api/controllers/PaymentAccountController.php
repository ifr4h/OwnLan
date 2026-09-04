<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\InstructorPaymentAccountService;
use app\services\OnlinePaymentService;
use app\services\PackageOfferingService;
use app\services\PaymentRefundService;
use app\services\PaymentReceiptService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

class PaymentAccountController extends BaseApiController
{
    private InstructorPaymentAccountService $accounts;
    private PackageOfferingService $offerings;
    private OnlinePaymentService $payments;
    private PaymentRefundService $refunds;
    private PaymentReceiptService $receipts;

    public function init(): void
    {
        parent::init();
        $this->accounts = new InstructorPaymentAccountService();
        $this->offerings = new PackageOfferingService();
        $this->payments = new OnlinePaymentService();
        $this->refunds = new PaymentRefundService();
        $this->receipts = new PaymentReceiptService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'settings' => ['GET'],
                    'onboard' => ['POST'],
                    'refresh' => ['POST'],
                    'booking-policy' => ['POST'],
                    'offerings' => ['GET', 'POST'],
                    'offering-update' => ['PUT', 'PATCH'],
                    'request-payment' => ['POST'],
                    'refund' => ['POST'],
                    'receipt' => ['GET'],
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

    public function actionSettings(): array
    {
        return $this->accounts->settings();
    }

    public function actionOnboard(): array
    {
        return $this->accounts->startOnboarding();
    }

    public function actionRefresh(): array
    {
        return $this->accounts->refreshStatus();
    }

    public function actionBookingPolicy(): array
    {
        $policy = (string) (Yii::$app->request->bodyParams['booking_payment_policy'] ?? '');

        return $this->accounts->updateBookingPaymentPolicy($policy);
    }

    public function actionOfferings(): array
    {
        if (Yii::$app->request->isPost) {
            return $this->offerings->create((array) Yii::$app->request->bodyParams);
        }

        return $this->offerings->list();
    }

    public function actionOfferingUpdate(int $id): array
    {
        return $this->offerings->update($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionRequestPayment(int $learnerId): array
    {
        $chargeId = Yii::$app->request->bodyParams['lesson_charge_id'] ?? null;

        return $this->payments->createPaymentRequest(
            $learnerId,
            $chargeId !== null ? (int) $chargeId : null,
        );
    }

    public function actionRefund(int $id): array
    {
        return $this->refunds->refund($id);
    }

    public function actionReceipt(int $id): array
    {
        return $this->receipts->receiptForPayment($id);
    }
}
