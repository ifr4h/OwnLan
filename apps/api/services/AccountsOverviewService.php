<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\TeachingValueResolver;
use app\components\TenantContext;
use app\models\FinancialGoal;
use app\models\Lesson;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Accounts 2.0 overview metrics — teaching income, booked value, projection, goals, capacity.
 */
class AccountsOverviewService
{
    private TeachingCapacityService $capacity;

    public function __construct(?TeachingCapacityService $capacity = null)
    {
        $this->capacity = $capacity ?? new TeachingCapacityService();
    }

    /**
     * @return array<string, mixed>
     */
    public function enrichOverview(
        Organisation $org,
        DateTimeImmutable $fromLocal,
        DateTimeImmutable $toInclusive,
        array $baseOverview,
    ): array {
        $tz = OrganisationTime::timezoneFor($org);
        $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
        $toExclusive = $toInclusive->modify('+1 day');
        $fromUtc = $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = $toExclusive->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $fromDay = $fromLocal->format('Y-m-d');
        $toDay = $toInclusive->format('Y-m-d');

        $teachingIncome = $this->sumTeachingIncome($org, $fromUtc, $toUtc);
        $bookedValue = $this->sumFutureBookedValue($org, $today, $toInclusive);
        $projected = $teachingIncome + $bookedValue;
        $avgHourly = $this->averageTeachingValuePerHour($org, $fromUtc, $toUtc);
        $goal = $this->goalForPeriod($org, (int) $fromLocal->format('Y'), (int) $fromLocal->format('n'));

        $goalGap = null;
        $estimatedHours = null;
        if ($goal !== null) {
            $goalGap = max(0, (int) $goal['target_pence'] - $projected);
            if ($avgHourly['pence_per_hour'] > 0 && $goalGap > 0) {
                $estimatedHours = round($goalGap / $avgHourly['pence_per_hour'], 1);
            }
        }

        $capacityFrom = $today > $fromLocal ? $today : $fromLocal;
        $capacity = $this->capacity->capacityForPeriod($org, $capacityFrom, $toInclusive);

        $isPartialMonth = $toInclusive->format('Y-m') === $today->format('Y-m')
            && $toInclusive < $today->modify('last day of this month');

        $monthTrend = $this->monthlyTrend($org, 6);

        return array_merge($baseOverview, [
            'period_is_partial' => $isPartialMonth,
            'teaching_income_pence' => $teachingIncome,
            'teaching_income_label' => Money::formatPence($teachingIncome),
            'booked_before_period_end_pence' => $bookedValue,
            'booked_before_period_end_label' => Money::formatPence($bookedValue),
            'projected_from_bookings_pence' => $projected,
            'projected_from_bookings_label' => Money::formatPence($projected),
            'goal' => $goal,
            'goal_gap_from_bookings_pence' => $goalGap,
            'goal_gap_from_bookings_label' => $goalGap !== null ? Money::formatPence($goalGap) : null,
            'average_teaching_value' => $avgHourly,
            'estimated_additional_teaching_hours' => $estimatedHours,
            'estimated_additional_teaching_label' => $estimatedHours !== null
                ? rtrim(rtrim(number_format($estimatedHours, 1, '.', ''), '0'), '.') . 'h'
                : null,
            'diary_capacity' => [
                'usable_minutes' => $capacity['usable_minutes'],
                'usable_hours_label' => $capacity['usable_hours_label'],
                'pupil_gap_matches' => $capacity['pupil_gap_matches'],
                'distinct_pupils_matching' => $capacity['distinct_pupils_matching'],
                'gaps' => $capacity['gaps'],
                'diary_path' => '/lessons?date=' . $capacityFrom->format('Y-m-d') . '&view=week',
            ],
            'monthly_trend' => $monthTrend,
            'definitions' => $this->metricDefinitions(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function metricDefinitions(): array
    {
        return [
            'teaching_income' => 'Value of completed lessons in this period (package credit at org hourly rate). Excludes waived lessons.',
            'money_received' => 'Cash and transfers recorded with counts_as_income, including package purchases.',
            'still_owed' => 'Outstanding lesson charges right now.',
            'booked_before_period_end' => 'Future scheduled lessons before period end at effective lesson price.',
            'projected_from_bookings' => 'Teaching income plus future booked value. Not a guarantee.',
            'average_teaching_value' => 'Completed lesson value divided by completed teaching minutes in period.',
            'diary_capacity' => 'Usable future gaps within working hours, each at least 60 minutes.',
        ];
    }

    private function sumTeachingIncome(Organisation $org, string $fromUtc, string $toUtc): int
    {
        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_COMPLETED])
            ->andWhere(['not', ['settlement' => FinanceService::SETTLEMENT_WAIVED]])
            ->andWhere(['>=', 'completed_at', $fromUtc])
            ->andWhere(['<', 'completed_at', $toUtc])
            ->all();

        $total = 0;
        foreach ($lessons as $lesson) {
            $total += TeachingValueResolver::effectiveValuePence($lesson, $org);
        }

        // Charged no-shows count financially but are not teaching hours.
        /** @var Lesson[] $noShows */
        $noShows = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_NO_SHOW])
            ->andWhere(['not', ['settlement' => FinanceService::SETTLEMENT_WAIVED]])
            ->andWhere(['>=', 'no_show_at', $fromUtc])
            ->andWhere(['<', 'no_show_at', $toUtc])
            ->all();
        foreach ($noShows as $lesson) {
            $total += TeachingValueResolver::effectiveValuePence($lesson, $org);
        }

        return $total;
    }

    private function sumFutureBookedValue(
        Organisation $org,
        DateTimeImmutable $fromLocal,
        DateTimeImmutable $toInclusive,
    ): int {
        $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $toUtc = $toInclusive->modify('+1 day')
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $nowUtc])
            ->andWhere(['<', 'starts_at', $toUtc])
            ->all();

        $total = 0;
        foreach ($lessons as $lesson) {
            $total += TeachingValueResolver::effectiveValuePence($lesson, $org);
        }

        return $total;
    }

    /**
     * @return array{pence_per_hour: int, label: string, teaching_minutes: int}
     */
    private function averageTeachingValuePerHour(Organisation $org, string $fromUtc, string $toUtc): array
    {
        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_COMPLETED])
            ->andWhere(['not', ['settlement' => FinanceService::SETTLEMENT_WAIVED]])
            ->andWhere(['>=', 'completed_at', $fromUtc])
            ->andWhere(['<', 'completed_at', $toUtc])
            ->all();

        $value = 0;
        $minutes = 0;
        foreach ($lessons as $lesson) {
            $value += TeachingValueResolver::effectiveValuePence($lesson, $org);
            $minutes += TeachingValueResolver::countsAsTeachingMinutes($lesson);
        }

        if ($minutes <= 0) {
            $rate = TeachingValueResolver::hourlyRatePence($org);

            return [
                'pence_per_hour' => $rate,
                'label' => $rate > 0 ? Money::formatPence($rate) . '/hr' : '—',
                'teaching_minutes' => 0,
            ];
        }

        $pencePerHour = (int) round(($value * 60) / $minutes);

        return [
            'pence_per_hour' => $pencePerHour,
            'label' => Money::formatPence($pencePerHour) . '/hr',
            'teaching_minutes' => $minutes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function goalForPeriod(Organisation $org, int $year, int $month): ?array
    {
        /** @var FinancialGoal|null $goal */
        $goal = FinancialGoal::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'period_year' => $year,
                'period_month' => $month,
            ])
            ->one();
        if ($goal === null) {
            return null;
        }

        $monthName = DateTimeImmutable::createFromFormat('!Y-n-j', "{$year}-{$month}-1")
            ?->format('F');

        return [
            'id' => (int) $goal->id,
            'period_year' => $year,
            'period_month' => $month,
            'period_label' => ($monthName ?? 'Month') . ' goal',
            'target_pence' => (int) $goal->target_pence,
            'target_label' => Money::formatPence((int) $goal->target_pence),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function monthlyTrend(Organisation $org, int $months): array
    {
        $tz = OrganisationTime::timezoneFor($org);
        $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
        $rows = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $today->modify('first day of this month')->modify("-{$i} months");
            $monthEnd = $monthStart->modify('last day of this month');
            $rangeEnd = $monthEnd > $today ? $today : $monthEnd;
            $fromUtc = $monthStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $toUtc = $rangeEnd->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

            $income = $this->sumTeachingIncome($org, $fromUtc, $toUtc);
            $isCurrent = $monthStart->format('Y-m') === $today->format('Y-m');

            $rows[] = [
                'month' => $monthStart->format('Y-m'),
                'label' => strtoupper($monthStart->format('M')),
                'teaching_income_pence' => $income,
                'teaching_income_label' => Money::formatPence($income),
                'is_current' => $isCurrent,
                'is_partial' => $isCurrent,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function upsertGoal(Organisation $org, int $year, int $month, int $targetPence): array
    {
        if ($month < 1 || $month > 12) {
            throw new \yii\web\BadRequestHttpException('Month must be 1–12.');
        }
        if ($targetPence <= 0) {
            throw new \yii\web\BadRequestHttpException('Goal must be greater than zero.');
        }

        $now = gmdate('Y-m-d H:i:s');
        /** @var FinancialGoal|null $goal */
        $goal = FinancialGoal::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'period_year' => $year,
                'period_month' => $month,
            ])
            ->one();

        if ($goal === null) {
            $goal = new FinancialGoal();
            $goal->organisation_id = (int) $org->id;
            $goal->period_year = $year;
            $goal->period_month = $month;
            $goal->created_at = $now;
        }
        $goal->target_pence = $targetPence;
        $goal->updated_at = $now;
        if (!$goal->save()) {
            throw new \yii\web\BadRequestHttpException('Could not save goal.');
        }

        $result = $this->goalForPeriod($org, $year, $month);

        return $result ?? [];
    }

    public function deleteGoal(Organisation $org, int $year, int $month): void
    {
        FinancialGoal::deleteAll([
            'organisation_id' => (int) $org->id,
            'period_year' => $year,
            'period_month' => $month,
        ]);
    }
}
