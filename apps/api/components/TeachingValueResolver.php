<?php

declare(strict_types=1);

namespace app\components;

use app\models\Lesson;
use app\models\Organisation;
use app\services\FinanceService;

/**
 * Effective teaching value for completed/scheduled lessons.
 *
 * Formula (documented in docs/22-accounts-2.md):
 * - Waived lessons: £0
 * - price_pence > 0: use stored price
 * - Otherwise: org default hourly rate × duration (package-delivered lessons included)
 */
final class TeachingValueResolver
{
    public static function effectiveValuePence(Lesson $lesson, Organisation $org): int
    {
        if ($lesson->settlement === FinanceService::SETTLEMENT_WAIVED) {
            return 0;
        }
        if ($lesson->price_pence !== null && (int) $lesson->price_pence > 0) {
            return (int) $lesson->price_pence;
        }
        $rate = $org->default_hourly_rate_pence;
        if ($rate !== null && (int) $rate > 0) {
            return Money::lessonPriceFromHourlyRate((int) $rate, (int) $lesson->duration_minutes);
        }

        return 0;
    }

    public static function countsAsTeachingMinutes(Lesson $lesson): int
    {
        if ($lesson->status !== Lesson::STATUS_COMPLETED) {
            return 0;
        }

        return max(0, (int) $lesson->duration_minutes);
    }

    public static function hourlyRatePence(Organisation $org): int
    {
        return max(0, (int) ($org->default_hourly_rate_pence ?? 0));
    }
}
