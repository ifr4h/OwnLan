<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\PortalContext;
use app\components\TheoryCertificate;
use app\models\Instructor;
use app\models\Learner;
use app\models\LearnerPackage;
use app\models\Lesson;
use app\models\LessonBookingRequest;
use app\models\LessonCharge;
use app\models\LessonRoute;
use app\models\Organisation;
use app\models\PackageCreditUsage;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Learner-visible portal — never exposes instructor-private or multi-pupil data.
 */
class PortalHomeService
{
    private ProgressService $progress;
    private LessonRouteService $routes;

    public function __construct(?ProgressService $progress = null, ?LessonRouteService $routes = null)
    {
        $this->progress = $progress ?? new ProgressService();
        $this->routes = $routes ?? new LessonRouteService($this->progress);
    }

    /**
     * Smart home feed — prioritises what matters now.
     *
     * @return array<string, mixed>
     */
    public function home(): array
    {
        [$learner, $org, $instructor] = $this->requireContext();
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowSql = $nowUtc->format('Y-m-d H:i:s');

        /** @var Lesson[] $upcoming */
        $upcoming = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $nowSql])
            ->orderBy(['starts_at' => SORT_ASC])
            ->limit(12)
            ->all();

        /** @var Lesson[] $previous */
        $previous = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['status' => [
                Lesson::STATUS_COMPLETED,
                Lesson::STATUS_CANCELLED,
                Lesson::STATUS_NO_SHOW,
            ]])
            ->orderBy(['starts_at' => SORT_DESC])
            ->limit(12)
            ->all();

        $next = $upcoming[0] ?? null;
        $restUpcoming = $next !== null ? array_slice($upcoming, 1) : [];

        $tests = new TestJourneyService();
        $journey = $tests->build($learner, $org, $nowUtc);
        $stats = $this->journeyStats((int) $learner->id, (int) $org->id, $org);
        $package = $this->packageAndBalance((int) $learner->id);
        $theory = TheoryCertificate::statusPayload(
            $learner->theory_status,
            $learner->theory_pass_date,
            $nowUtc,
        );
        $progressPayload = $this->progress->learnerProgress((int) $learner->id, (int) $org->id);
        $routeCount = (int) LessonRoute::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'learner_id' => (int) $learner->id,
                'learner_visible' => true,
                'status' => LessonRoute::STATUS_COMPLETED,
                'deleted_at' => null,
            ])
            ->count();

        $latestCompleted = null;
        foreach ($previous as $lesson) {
            if ($lesson->status === Lesson::STATUS_COMPLETED) {
                $latestCompleted = $lesson;
                break;
            }
        }

        $insights = $this->buildHomeInsights(
            $stats,
            $progressPayload,
            $package,
            $routeCount,
            $next,
            $upcoming,
            $learner,
            $org,
        );

        $priority = $this->resolvePriority(
            $next,
            $journey,
            $latestCompleted,
            $package,
            $nowUtc,
            $org,
        );

        $nextSkills = $latestCompleted !== null
            ? $this->progress->skillsForLesson((int) $latestCompleted->id, (int) $org->id)
            : [];

        return [
            'learner' => [
                'first_name' => $learner->first_name,
                'full_name' => $learner->fullName,
            ],
            'greeting' => $this->greeting($nowUtc, $org, $learner->first_name),
            'instructor' => [
                'display_name' => $instructor?->display_name ?? $org->name,
                'business_name' => $org->name,
                'contact_phone' => $org->contact_phone,
                'contact_email' => $org->contact_email,
                'service_area' => $org->service_area,
                'cancellation_policy' => $org->cancellation_policy,
            ],
            'priority' => $priority,
            'next_lesson' => $next !== null ? $this->serializeLesson($next, $org) : null,
            'upcoming_lessons' => array_map(
                fn (Lesson $l) => $this->serializeLesson($l, $org),
                $restUpcoming,
            ),
            'previous_lessons' => array_map(
                fn (Lesson $l) => $this->serializeLesson($l, $org),
                $previous,
            ),
            'progress' => [
                'last_lesson_summary' => $learner->last_lesson_summary,
                'next_focus' => $learner->next_focus,
                'summary' => $progressPayload['summary'],
                'insights' => array_slice($progressPayload['insights'], 0, 3),
            ],
            'journey' => $stats,
            'practical_test' => $this->serializeTest($journey),
            'theory' => $this->serializeTheory($theory),
            'package_and_balance' => $package,
            'routes' => [
                'count' => $routeCount,
            ],
            'insights' => $insights,
            'booking' => $this->bookingContext($org, $next, (int) $learner->id),
            'recent_recap' => $latestCompleted !== null
                ? $this->serializeRecap($latestCompleted, $org, $nextSkills)
                : null,
            'synced_at' => $nowUtc->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function journey(): array
    {
        [$learner, $org] = $this->requireContextLearnerOrg();
        $stats = $this->journeyStats((int) $learner->id, (int) $org->id, $org);
        $history = $this->drivingHistory((int) $learner->id, (int) $org->id, $org, 40);
        $hoursByMonth = $this->hoursByMonth((int) $learner->id, (int) $org->id, $org, 8);

        return [
            'stats' => $stats,
            'started_on' => $stats['started_on'],
            'started_on_display' => $stats['started_on_display'],
            'current_focus' => $learner->next_focus,
            'history' => $history,
            'hours_by_month' => $hoursByMonth,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(): array
    {
        [$learner, $org] = $this->requireContextLearnerOrg();

        return $this->progress->learnerProgress((int) $learner->id, (int) $org->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function money(): array
    {
        [$learner, $org] = $this->requireContextLearnerOrg();
        $balance = $this->packageAndBalance((int) $learner->id);
        $activity = $this->creditActivity((int) $learner->id, (int) $org->id, $org);

        return [
            'credit' => $balance,
            'activity' => $activity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recap(int $lessonId): array
    {
        [$learner, $org] = $this->requireContextLearnerOrg();
        $lesson = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere([
                'id' => $lessonId,
                'status' => Lesson::STATUS_COMPLETED,
            ])
            ->one();
        if (!$lesson instanceof Lesson) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        $skills = $this->progress->skillsForLesson((int) $lesson->id, (int) $org->id);
        $route = LessonRoute::findOne([
            'lesson_id' => (int) $lesson->id,
            'organisation_id' => (int) $org->id,
            'learner_id' => (int) $learner->id,
            'learner_visible' => true,
            'status' => LessonRoute::STATUS_COMPLETED,
            'deleted_at' => null,
        ]);

        $payload = $this->serializeRecap($lesson, $org, $skills);
        $payload['route_id'] = $route !== null ? (int) $route->id : null;

        $mock = (new MockTestService())->completedForLesson((int) $lesson->id, (int) $org->id);
        if ($mock !== null) {
            $duration = null;
            if ($mock->finished_at !== null) {
                $start = strtotime($mock->started_at . ' UTC');
                $end = strtotime($mock->finished_at . ' UTC');
                if ($start !== false && $end !== false) {
                    $secs = max(0, $end - $start);
                    $duration = sprintf('%d:%02d', intdiv($secs, 60), $secs % 60);
                }
            }
            $payload['mock_test'] = [
                'id' => (int) $mock->id,
                'elapsed_display' => $duration,
                'driving_faults_count' => (int) $mock->driving_faults_count,
                'serious_faults_count' => (int) $mock->serious_faults_count,
                'dangerous_faults_count' => (int) $mock->dangerous_faults_count,
                'result' => $mock->result,
                'result_label' => $mock->result === \app\models\MockTest::RESULT_PASS
                    ? 'Pass standard'
                    : 'Not at pass standard',
                'learner_summary' => $mock->learner_summary,
                'instructor_note' => $mock->instructor_note,
                'suggested_next_focus' => $mock->suggested_next_focus,
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function prepare(): array
    {
        [$learner, $org] = $this->requireContextLearnerOrg();
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $next = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $nowUtc->format('Y-m-d H:i:s')])
            ->orderBy(['starts_at' => SORT_ASC])
            ->one();

        $latest = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_COMPLETED])
            ->orderBy(['starts_at' => SORT_DESC])
            ->one();

        $route = null;
        if ($latest instanceof Lesson) {
            $route = LessonRoute::findOne([
                'lesson_id' => (int) $latest->id,
                'organisation_id' => (int) $org->id,
                'learner_visible' => true,
                'status' => LessonRoute::STATUS_COMPLETED,
                'deleted_at' => null,
            ]);
        }

        return [
            'next_lesson' => $next instanceof Lesson ? $this->serializeLesson($next, $org) : null,
            'focus' => $learner->next_focus,
            'last_lesson_id' => $latest instanceof Lesson ? (int) $latest->id : null,
            'last_route_id' => $route !== null ? (int) $route->id : null,
        ];
    }

    /**
     * @return array{0: Learner, 1: Organisation, 2: Instructor|null}
     */
    private function requireContext(): array
    {
        $account = PortalContext::requireAccount();
        /** @var Learner|null $learner */
        $learner = Learner::findOne([
            'id' => (int) $account->learner_id,
            'organisation_id' => (int) $account->organisation_id,
        ]);
        if ($learner === null || $learner->archived_at !== null) {
            throw new NotFoundHttpException('Your learner record is unavailable.');
        }

        /** @var Organisation|null $org */
        $org = Organisation::findOne(['id' => (int) $account->organisation_id]);
        if ($org === null) {
            throw new NotFoundHttpException('Business not found.');
        }

        /** @var Instructor|null $instructor */
        $instructor = Instructor::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        return [$learner, $org, $instructor];
    }

    /**
     * @return array{0: Learner, 1: Organisation}
     */
    private function requireContextLearnerOrg(): array
    {
        [$learner, $org] = array_slice($this->requireContext(), 0, 2);

        return [$learner, $org];
    }

    /**
     * @return array<string, mixed>
     */
    private function journeyStats(int $learnerId, int $organisationId, Organisation $org): array
    {
        $completed = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_COMPLETED])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $totalMinutes = 0;
        $lessonCount = count($completed);
        $thisMonthMinutes = 0;
        $tz = OrganisationTime::timezoneFor($org);
        $nowLocal = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($tz);
        $monthKey = $nowLocal->format('Y-m');

        $first = $completed[0] ?? null;
        foreach ($completed as $lesson) {
            $mins = (int) $lesson->duration_minutes;
            $totalMinutes += $mins;
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            if ($local->format('Y-m') === $monthKey) {
                $thisMonthMinutes += $mins;
            }
        }

        $avg = $lessonCount > 0 ? (int) round($totalMinutes / $lessonCount) : 0;
        $startedOn = null;
        $startedDisplay = null;
        if ($first instanceof Lesson) {
            $local = OrganisationTime::utcToLocal($first->starts_at, $org);
            $startedOn = $local->format('Y-m-d');
            $startedDisplay = $local->format('j M Y');
        }

        return [
            'lessons_completed' => $lessonCount,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'total_hours_label' => $this->hoursLabel($totalMinutes),
            'average_duration_minutes' => $avg,
            'hours_this_month' => round($thisMonthMinutes / 60, 1),
            'hours_this_month_label' => $this->hoursLabel($thisMonthMinutes),
            'started_on' => $startedOn,
            'started_on_display' => $startedDisplay,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drivingHistory(int $learnerId, int $organisationId, Organisation $org, int $limit): array
    {
        /** @var Lesson[] $lessons */
        $lessons = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_COMPLETED])
            ->orderBy(['starts_at' => SORT_DESC])
            ->limit($limit)
            ->all();

        $out = [];
        foreach ($lessons as $lesson) {
            $skills = $this->progress->skillsForLesson((int) $lesson->id, $organisationId);
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $title = $this->historyTitle($lesson, $skills);
            $out[] = [
                'id' => (int) $lesson->id,
                'date_key' => $local->format('Y-m-d'),
                'date_label' => strtoupper($local->format('j M')),
                'title' => $title,
                'duration_minutes' => (int) $lesson->duration_minutes,
                'duration_label' => $this->durationHoursLabel((int) $lesson->duration_minutes),
                'learner_summary' => $lesson->learner_summary,
                'next_focus' => $lesson->next_focus,
                'skills' => array_map(static fn (array $s) => $s['label'], $skills),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{label: string}> $skills
     */
    private function historyTitle(Lesson $lesson, array $skills): string
    {
        if ($skills !== []) {
            $labels = array_map(static fn (array $s) => $s['label'], array_slice($skills, 0, 3));

            return implode(' & ', $labels);
        }
        if ($lesson->next_focus) {
            return $lesson->next_focus;
        }
        if ($lesson->learner_summary) {
            $snip = trim($lesson->learner_summary);
            if (strlen($snip) > 48) {
                return rtrim(substr($snip, 0, 45)) . '…';
            }

            return $snip;
        }

        return 'Driving lesson';
    }

    /**
     * @return list<array{month: string, label: string, minutes: int, hours: float, lesson_ids: list<int>}>
     */
    private function hoursByMonth(int $learnerId, int $organisationId, Organisation $org, int $months): array
    {
        /** @var Lesson[] $lessons */
        $lessons = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['status' => Lesson::STATUS_COMPLETED])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $buckets = [];
        foreach ($lessons as $lesson) {
            $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
            $key = $local->format('Y-m');
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'month' => $key,
                    'label' => $local->format('M Y'),
                    'minutes' => 0,
                    'hours' => 0.0,
                    'lesson_ids' => [],
                ];
            }
            $buckets[$key]['minutes'] += (int) $lesson->duration_minutes;
            $buckets[$key]['lesson_ids'][] = (int) $lesson->id;
        }

        foreach ($buckets as &$bucket) {
            $bucket['hours'] = round($bucket['minutes'] / 60, 1);
        }
        unset($bucket);

        $list = array_values($buckets);

        return array_slice($list, -$months);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function creditActivity(int $learnerId, int $organisationId, Organisation $org): array
    {
        $items = [];

        /** @var PackageCreditUsage[] $usages */
        $usages = PackageCreditUsage::find()
            ->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => $organisationId,
            ])
            ->andWhere(['voided_at' => null])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(30)
            ->all();

        foreach ($usages as $usage) {
            $local = OrganisationTime::utcToLocal($usage->created_at, $org);
            $items[] = [
                'id' => 'usage-' . $usage->id,
                'type' => 'lesson',
                'date_label' => $local->format('j M'),
                'sort_at' => $usage->created_at,
                'label' => 'Lesson',
                'minutes_delta' => -1 * (int) $usage->minutes,
                'minutes_label' => '-' . $this->durationHoursLabel((int) $usage->minutes),
            ];
        }

        /** @var LearnerPackage[] $packages */
        $packages = LearnerPackage::find()
            ->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => $organisationId,
            ])
            ->andWhere(['!=', 'status', LearnerPackage::STATUS_VOIDED])
            ->orderBy(['purchased_at' => SORT_DESC])
            ->limit(20)
            ->all();

        foreach ($packages as $package) {
            $local = OrganisationTime::utcToLocal($package->purchased_at, $org);
            $items[] = [
                'id' => 'pkg-' . $package->id,
                'type' => 'package_added',
                'date_label' => $local->format('j M'),
                'sort_at' => $package->purchased_at,
                'label' => 'Package added',
                'minutes_delta' => (int) $package->purchased_minutes,
                'minutes_label' => '+' . $this->durationHoursLabel((int) $package->purchased_minutes),
            ];
        }

        usort($items, static fn (array $a, array $b) => strcmp((string) $b['sort_at'], (string) $a['sort_at']));

        return array_map(static function (array $item) {
            unset($item['sort_at']);

            return $item;
        }, array_slice($items, 0, 40));
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $progressPayload
     * @param array<string, mixed> $package
     * @param Lesson[] $upcoming
     * @return list<string>
     */
    private function buildHomeInsights(
        array $stats,
        array $progressPayload,
        array $package,
        int $routeCount,
        ?Lesson $next,
        array $upcoming,
        Learner $learner,
        Organisation $org,
    ): array {
        $insights = [];

        if (($stats['hours_this_month'] ?? 0) > 0) {
            $insights[] = 'You\'ve driven ' . $stats['hours_this_month_label'] . ' this month.';
        }
        foreach (array_slice($progressPayload['insights'] ?? [], 0, 2) as $line) {
            $insights[] = $line;
        }
        if ($routeCount > 0) {
            $insights[] = $routeCount === 1
                ? 'You have 1 recorded route you can review.'
                : 'You have ' . $routeCount . ' recorded routes you can review.';
        }
        if ($next === null) {
            $insights[] = 'No future lesson is booked yet.';
            if ($org->bookingMode() !== Organisation::BOOKING_MODE_MANUAL) {
                $insights[] = 'You can find a time that suits you in the portal.';
            } else {
                $insights[] = 'Ask your instructor when you\'re ready for another lesson.';
            }
        } elseif (count($upcoming) === 1) {
            $insights[] = 'Your next lesson is your only booked lesson.';
        }
        if (!empty($package['has_credit']) && ($package['credit_minutes'] ?? 0) > 0 && ($package['credit_minutes'] ?? 0) <= 120) {
            $insights[] = 'Lesson credit is getting low — ' . $package['credit_label'] . '.';
        }
        if ($learner->next_focus) {
            $insights[] = 'Current focus: ' . $learner->next_focus . '.';
        }

        return array_values(array_unique(array_slice($insights, 0, 5)));
    }

    /**
     * @param array<string, mixed>|null $journey
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function resolvePriority(
        ?Lesson $next,
        ?array $journey,
        ?Lesson $latestCompleted,
        array $package,
        DateTimeImmutable $nowUtc,
        Organisation $org,
    ): array {
        if ($next !== null) {
            $local = OrganisationTime::utcToLocal($next->starts_at, $org);
            $today = $nowUtc->setTimezone(OrganisationTime::timezoneFor($org))->format('Y-m-d');
            $lessonDay = $local->format('Y-m-d');
            $kind = $lessonDay === $today ? 'lesson_today' : 'lesson_upcoming';
            $tomorrow = (new DateTimeImmutable($today, OrganisationTime::timezoneFor($org)))
                ->modify('+1 day')
                ->format('Y-m-d');
            if ($lessonDay === $tomorrow) {
                $kind = 'lesson_tomorrow';
            }

            return [
                'kind' => $kind,
                'lesson_id' => (int) $next->id,
            ];
        }

        if ($journey !== null && isset($journey['days_until']) && (int) $journey['days_until'] <= 28 && (int) $journey['days_until'] >= 0) {
            return ['kind' => 'test_approaching'];
        }

        if ($latestCompleted !== null) {
            $completedAt = $latestCompleted->completed_at ?? $latestCompleted->starts_at;
            $done = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $completedAt, new DateTimeZone('UTC'));
            $recapWindow = $nowUtc->modify('-3 days');
            if ($done !== false && $done > $recapWindow) {
                return [
                    'kind' => 'new_recap',
                    'lesson_id' => (int) $latestCompleted->id,
                ];
            }
        }

        if (!empty($package['has_credit']) && ($package['credit_minutes'] ?? 0) <= 90) {
            return ['kind' => 'package_low'];
        }

        if ($next === null) {
            return ['kind' => 'no_booking'];
        }

        return ['kind' => 'insight'];
    }

    /**
     * @param list<array{label: string}> $skills
     * @return array<string, mixed>
     */
    private function serializeRecap(Lesson $lesson, Organisation $org, array $skills): array
    {
        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);

        return [
            'lesson_id' => (int) $lesson->id,
            'date_label' => $local->format('j F'),
            'is_today' => $local->format('Y-m-d') === (new DateTimeImmutable('now', OrganisationTime::timezoneFor($org)))->format('Y-m-d'),
            'duration_minutes' => (int) $lesson->duration_minutes,
            'duration_label' => $this->durationHoursLabel((int) $lesson->duration_minutes),
            'skills' => array_map(static fn (array $s) => $s['label'], $skills),
            'learner_summary' => $lesson->learner_summary,
            'next_focus' => $lesson->next_focus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLesson(Lesson $lesson, Organisation $org): array
    {
        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);
        $ends = $local->modify('+' . (int) $lesson->duration_minutes . ' minutes');
        $skills = $lesson->status === Lesson::STATUS_COMPLETED
            ? $this->progress->skillsForLesson((int) $lesson->id, (int) $org->id)
            : [];

        return [
            'id' => (int) $lesson->id,
            'starts_at' => $lesson->starts_at,
            'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
            'starts_at_day' => $this->relativeDayLabel($local, $org),
            'starts_at_time' => $local->format('g:i A'),
            'ends_at_time' => $ends->format('g:i A'),
            'duration_minutes' => (int) $lesson->duration_minutes,
            'duration_label' => $this->durationHoursLabel((int) $lesson->duration_minutes),
            'pickup_address' => $lesson->pickup_address,
            'pickup_short' => $this->shortPickup($lesson->pickup_address),
            'status' => $lesson->status,
            'status_label' => $this->learnerLessonStatusLabel((string) $lesson->status),
            'learner_summary' => $lesson->status === Lesson::STATUS_COMPLETED
                ? $lesson->learner_summary
                : null,
            'next_focus' => $lesson->status === Lesson::STATUS_COMPLETED
                ? $lesson->next_focus
                : null,
            'skills' => array_map(static fn (array $s) => $s['label'], $skills),
        ];
    }

    private function learnerLessonStatusLabel(string $status): string
    {
        return match ($status) {
            Lesson::STATUS_NO_SHOW => 'Not attended',
            Lesson::STATUS_CANCELLED => 'Cancelled',
            Lesson::STATUS_COMPLETED => 'Completed',
            default => 'Scheduled',
        };
    }

    private function relativeDayLabel(DateTimeImmutable $local, Organisation $org): string
    {
        $tz = OrganisationTime::timezoneFor($org);
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d');
        $day = $local->format('Y-m-d');
        if ($day === $today) {
            return 'Today';
        }
        $tomorrow = (new DateTimeImmutable($today, $tz))->modify('+1 day')->format('Y-m-d');
        if ($day === $tomorrow) {
            return 'Tomorrow';
        }

        return $local->format('l');
    }

    private function shortPickup(?string $address): ?string
    {
        if ($address === null || trim($address) === '') {
            return null;
        }
        $parts = array_map('trim', explode(',', $address));

        return $parts[1] ?? $parts[0];
    }

    /**
     * @param array<string, mixed>|null $journey
     * @return array<string, mixed>|null
     */
    private function serializeTest(?array $journey): ?array
    {
        if ($journey === null) {
            return null;
        }

        return [
            'test_date' => $journey['test_date'],
            'test_date_display' => $journey['test_date_display'],
            'test_centre' => $journey['test_centre'],
            'countdown_label' => $journey['countdown_label'],
            'days_until' => $journey['days_until'],
            'lessons_booked_before_test' => $journey['lessons_booked_before_test'],
        ];
    }

    /**
     * @param array<string, mixed>|null $theory
     * @return array<string, mixed>|null
     */
    private function serializeTheory(?array $theory): ?array
    {
        if ($theory === null) {
            return null;
        }

        // Soften "not yet" so it never reads as failure.
        if (($theory['status'] ?? '') === 'not_yet') {
            $theory['label'] = 'Theory not recorded yet';
        }

        return [
            'status' => $theory['status'],
            'label' => $theory['label'],
            'pass_date' => $theory['pass_date'],
            'expires_on' => $theory['expires_on'],
            'expires_on_display' => $theory['expires_on_display'],
            'days_until_expiry' => $theory['days_until_expiry'],
            'urgency' => $theory['urgency'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packageAndBalance(int $learnerId): array
    {
        $orgId = (int) PortalContext::requireAccount()->organisation_id;

        $creditMinutes = (int) (LearnerPackage::find()
            ->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => $orgId,
                'status' => LearnerPackage::STATUS_ACTIVE,
            ])
            ->sum('remaining_minutes') ?? 0);

        $purchasedMinutes = (int) (LearnerPackage::find()
            ->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => $orgId,
            ])
            ->andWhere(['!=', 'status', LearnerPackage::STATUS_VOIDED])
            ->sum('purchased_minutes') ?? 0);

        $usedMinutes = max(0, $purchasedMinutes - $creditMinutes);

        $owed = 0;
        /** @var LessonCharge[] $charges */
        $charges = LessonCharge::find()
            ->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => $orgId,
                'status' => LessonCharge::STATUS_OUTSTANDING,
            ])
            ->all();
        foreach ($charges as $charge) {
            $owed += $charge->outstandingPence();
        }

        $pct = $purchasedMinutes > 0
            ? (int) round(($usedMinutes / $purchasedMinutes) * 100)
            : 0;

        return [
            'credit_minutes' => $creditMinutes,
            'credit_label' => $this->creditLabel($creditMinutes),
            'credit_hours' => round($creditMinutes / 60, 1),
            'has_credit' => $creditMinutes > 0,
            'purchased_minutes' => $purchasedMinutes,
            'purchased_hours' => round($purchasedMinutes / 60, 1),
            'used_minutes' => $usedMinutes,
            'used_hours' => round($usedMinutes / 60, 1),
            'used_percent' => min(100, $pct),
            'amount_due_pence' => $owed,
            'amount_due_label' => Money::formatPence($owed),
            'has_amount_due' => $owed > 0,
        ];
    }

    private function greeting(DateTimeImmutable $nowUtc, Organisation $org, string $firstName): string
    {
        $hour = (int) $nowUtc->setTimezone(OrganisationTime::timezoneFor($org))->format('G');
        $part = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        return $part . ', ' . $firstName;
    }

    private function creditLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'No lesson credit remaining';
        }

        return $this->hoursLabel($minutes) . ' remaining';
    }

    private function hoursLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 hours';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($hours > 0 && $rem === 0) {
            return $hours === 1 ? '1 hour' : $hours . ' hours';
        }
        if ($hours > 0) {
            return $hours . 'h ' . $rem . 'm';
        }

        return $minutes . ' minutes';
    }

    private function durationHoursLabel(int $minutes): string
    {
        if ($minutes % 60 === 0) {
            $h = intdiv($minutes, 60);

            return $h === 1 ? '1 hr' : $h . ' hrs';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m . ' min';
        }

        return $h . 'h ' . $m . 'm';
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingContext(Organisation $org, ?Lesson $next, int $learnerId): array
    {
        $mode = $org->bookingMode();
        $canBook = $mode !== Organisation::BOOKING_MODE_MANUAL;

        /** @var LessonBookingRequest|null $openRequest */
        $openRequest = LessonBookingRequest::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'learner_id' => $learnerId,
            ])
            ->andWhere(['status' => [
                LessonBookingRequest::STATUS_PENDING,
                LessonBookingRequest::STATUS_COUNTER_PROPOSED,
            ]])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        $ctaLabel = match ($mode) {
            Organisation::BOOKING_MODE_REQUEST => 'Request a lesson',
            Organisation::BOOKING_MODE_INSTANT => 'Book a lesson',
            default => null,
        };
        if ($next !== null && $org->learnerRescheduleMode() !== Organisation::BOOKING_MODE_MANUAL) {
            $ctaLabel = 'Find another time';
        } elseif ($canBook && $next === null) {
            $ctaLabel = 'Find a time';
        }

        $openPayload = null;
        if ($openRequest !== null) {
            $local = OrganisationTime::utcToLocal($openRequest->requested_starts_at, $org);
            $openPayload = [
                'id' => (int) $openRequest->id,
                'status' => $openRequest->status,
                'starts_at_day' => $local->format('D j M'),
                'starts_at_time' => $local->format('H:i'),
            ];
        }

        return [
            'can_book' => $canBook,
            'booking_mode' => $mode,
            'reschedule_mode' => $org->learnerRescheduleMode(),
            'can_cancel' => $org->learnerCanCancel(),
            'cta_label' => $ctaLabel,
            'cta_path' => $canBook ? '/portal/book' : null,
            'open_request' => $openPayload,
        ];
    }
}
