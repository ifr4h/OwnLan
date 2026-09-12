<?php

declare(strict_types=1);

namespace app\components;

use app\models\LearnerServiceRate;
use app\models\Lesson;
use app\models\Organisation;
use app\models\OrganisationPricingRule;
use app\models\OrganisationService;
use app\models\ServicePriceChange;
use DateTimeImmutable;

/**
 * Resolve list / charge price for a lesson from the Services catalogue.
 *
 * Precedence (docs/27-services-pricing.md):
 * 1. Explicit override
 * 2. Stored lesson.price_pence
 * 3. Pupil-specific rate
 * 4. Service list price (incl. scheduled changes)
 * 5. Org hourly × duration
 * 6. Pricing rules (add / percent / set)
 */
final class ServicePriceResolver
{
    /**
     * @param array<string, mixed> $explicitData may include price_pence / price
     */
    public static function resolvePence(
        Organisation $org,
        ?Lesson $lesson,
        ?OrganisationService $service,
        ?int $learnerId,
        DateTimeImmutable $localStartsAt,
        int $durationMinutes,
        array $explicitData = [],
    ): int {
        if (isset($explicitData['price_pence']) || isset($explicitData['price'])) {
            return self::explicitPence($explicitData);
        }
        if ($lesson !== null && $lesson->price_pence !== null) {
            return max(0, (int) $lesson->price_pence);
        }

        $date = $localStartsAt->format('Y-m-d');
        $base = self::pupilRatePence($org, $learnerId, $service, $date);
        if ($base === null) {
            $base = self::serviceListPricePence($org, $service, $date, $durationMinutes);
        }

        return self::applyPricingRules($org, $service, $localStartsAt, $base);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function explicitPence(array $data): int
    {
        if (array_key_exists('price_pence', $data) && $data['price_pence'] !== null && $data['price_pence'] !== '') {
            return Money::requireNonNegativePence($data['price_pence'], 'Price');
        }
        if (array_key_exists('price', $data) && $data['price'] !== null && $data['price'] !== '') {
            $pence = Money::poundsToPence($data['price']);
            if ($pence < 0) {
                throw new \InvalidArgumentException('Price cannot be negative.');
            }

            return $pence;
        }

        throw new \InvalidArgumentException('Price is required.');
    }

    private static function pupilRatePence(
        Organisation $org,
        ?int $learnerId,
        ?OrganisationService $service,
        string $dateYmd,
    ): ?int {
        if ($learnerId === null || $learnerId <= 0) {
            return null;
        }

        $serviceId = $service !== null ? (int) $service->id : null;
        $q = LearnerServiceRate::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'learner_id' => $learnerId,
            ])
            ->andWhere(['<=', 'effective_from', $dateYmd])
            ->andWhere([
                'or',
                ['effective_to' => null],
                ['>=', 'effective_to', $dateYmd],
            ])
            ->orderBy(['effective_from' => SORT_DESC, 'id' => SORT_DESC]);

        if ($serviceId !== null) {
            /** @var LearnerServiceRate|null $specific */
            $specific = (clone $q)->andWhere(['service_id' => $serviceId])->one();
            if ($specific !== null) {
                return max(0, (int) $specific->price_pence);
            }
        }

        /** @var LearnerServiceRate|null $any */
        $any = (clone $q)->andWhere(['service_id' => null])->one();
        if ($any !== null) {
            return max(0, (int) $any->price_pence);
        }

        return null;
    }

    private static function serviceListPricePence(
        Organisation $org,
        ?OrganisationService $service,
        string $dateYmd,
        int $durationMinutes,
    ): int {
        if ($service !== null) {
            /** @var ServicePriceChange|null $change */
            $change = ServicePriceChange::find()
                ->andWhere([
                    'organisation_id' => (int) $org->id,
                    'service_id' => (int) $service->id,
                ])
                ->andWhere(['<=', 'effective_on', $dateYmd])
                ->orderBy(['effective_on' => SORT_DESC, 'id' => SORT_DESC])
                ->one();
            if ($change !== null) {
                return max(0, (int) $change->price_pence);
            }

            return max(0, (int) $service->price_pence);
        }

        $rate = $org->default_hourly_rate_pence;
        if ($rate !== null && (int) $rate > 0) {
            return Money::lessonPriceFromHourlyRate((int) $rate, $durationMinutes);
        }

        return 0;
    }

    private static function applyPricingRules(
        Organisation $org,
        ?OrganisationService $service,
        DateTimeImmutable $localStartsAt,
        int $basePence,
    ): int {
        $price = $basePence;
        $dow = (int) $localStartsAt->format('N'); // 1=Mon .. 7=Sun
        $hm = $localStartsAt->format('H:i');
        $serviceId = $service !== null ? (int) $service->id : null;

        /** @var OrganisationPricingRule[] $rules */
        $rules = OrganisationPricingRule::find()
            ->andWhere(['organisation_id' => (int) $org->id, 'active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($rules as $rule) {
            if ($rule->service_id !== null && (int) $rule->service_id !== $serviceId) {
                continue;
            }
            if (!self::ruleMatchesDay($rule, $dow)) {
                continue;
            }
            if (!self::ruleMatchesTime($rule, $hm)) {
                continue;
            }

            $value = (int) $rule->adjustment_value;
            if ($rule->adjustment_kind === OrganisationPricingRule::ADJUST_SET) {
                $price = max(0, $value);
            } elseif ($rule->adjustment_kind === OrganisationPricingRule::ADJUST_ADD) {
                $price = max(0, $price + $value);
            } elseif ($rule->adjustment_kind === OrganisationPricingRule::ADJUST_PERCENT) {
                // integer percent of current price, rounded to nearest penny
                $delta = (int) round($price * $value / 100);
                $price = max(0, $price + $delta);
            }
        }

        return $price;
    }

    private static function ruleMatchesDay(OrganisationPricingRule $rule, int $isoDow): bool
    {
        $raw = trim((string) $rule->days_of_week);
        if ($raw === '') {
            return true;
        }
        $days = array_filter(array_map('intval', explode(',', $raw)));

        return in_array($isoDow, $days, true);
    }

    private static function ruleMatchesTime(OrganisationPricingRule $rule, string $hm): bool
    {
        $after = $rule->time_after !== null && $rule->time_after !== '' ? (string) $rule->time_after : null;
        $before = $rule->time_before !== null && $rule->time_before !== '' ? (string) $rule->time_before : null;
        if ($after === null && $before === null) {
            return true;
        }
        if ($after !== null && strcmp($hm, $after) < 0) {
            return false;
        }
        if ($before !== null && strcmp($hm, $before) >= 0) {
            return false;
        }

        return true;
    }
}
