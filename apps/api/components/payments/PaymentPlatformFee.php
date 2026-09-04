<?php

declare(strict_types=1);

namespace app\components\payments;

use Yii;

/**
 * Central platform fee configuration — do not hard-code percentages in services.
 */
final class PaymentPlatformFee
{
    public static function applicationFeePence(int $grossPence): int
    {
        $bps = (int) (getenv('OWNLANE_PLATFORM_FEE_BPS') ?: 0);
        if ($bps <= 0 || $grossPence <= 0) {
            return 0;
        }

        return (int) floor($grossPence * $bps / 10000);
    }

    public static function feeDescription(): string
    {
        $bps = (int) (getenv('OWNLANE_PLATFORM_FEE_BPS') ?: 0);
        if ($bps <= 0) {
            return 'No OwnLane platform fee on online payments.';
        }
        $percent = number_format($bps / 100, 2);

        return 'OwnLane platform fee: ' . rtrim(rtrim($percent, '0'), '.') . '% per online payment.';
    }

    public static function stripePublishableKey(): string
    {
        return trim((string) (getenv('STRIPE_PUBLISHABLE_KEY') ?: ''));
    }
}
