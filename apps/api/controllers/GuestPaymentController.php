<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\OnlinePaymentService;
use Yii;
use yii\filters\VerbFilter;

/**
 * Guest payment links — scoped token access only.
 */
class GuestPaymentController extends BaseApiController
{
    private OnlinePaymentService $payments;

    public function init(): void
    {
        parent::init();
        $this->payments = new OnlinePaymentService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'view' => ['GET'],
                    'start' => ['POST'],
                    'confirm' => ['POST'],
                    'confirm' => ['POST'],
                ],
            ],
        ];
    }

    public function actionView(string $token): array
    {
        return $this->payments->guestCheckout($token);
    }

    public function actionConfirm(string $token): array
    {
        return $this->payments->guestConfirmCheckout($token);
    }
}
