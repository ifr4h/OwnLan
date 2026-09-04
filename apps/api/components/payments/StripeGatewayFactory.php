<?php

declare(strict_types=1);

namespace app\components\payments;

use Yii;

final class StripeGatewayFactory
{
    public static function make(): StripeGatewayInterface
    {
        if (Yii::$app->has('stripeGateway')) {
            /** @var StripeGatewayInterface $gateway */
            $gateway = Yii::$app->get('stripeGateway');

            return $gateway;
        }

        $gateway = new StripeGateway();
        if (!$gateway->isConfigured()) {
            return new FakeStripeGateway();
        }

        return $gateway;
    }
}
