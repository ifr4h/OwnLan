<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\OnlinePaymentService;
use app\services\StripeWebhookService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * Stripe webhooks — no session auth, signature verified.
 */
class StripeWebhookController extends BaseApiController
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'handle' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if ($action->id === 'handle') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actionHandle(): array
    {
        $payload = (string) Yii::$app->request->rawBody;
        $signature = (string) Yii::$app->request->headers->get('Stripe-Signature', '');

        return (new StripeWebhookService())->handle($payload, $signature);
    }
}
