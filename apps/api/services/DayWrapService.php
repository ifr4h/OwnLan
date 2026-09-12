<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\TeachingValueResolver;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\LessonCharge;
use app\models\LessonSkill;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * End-of-day admin recap: hours, money, and unfinished lesson admin.
 * Temp surface via GET /day-wrap before wiring into Today.
 */
final class DayWrapService
{
    /**
     * @return array<string, mixed>
     * @throws UnauthorizedHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function forDay(?DateTimeImmutable $nowUtc = null): array
    {
        $org = $this->requireOrganisation();
        $tz = OrganisationTime::timezoneFor($org);
        $nowUtc = $nowUtc?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowLocal = $nowUtc->setTimezone($tz);

        $dayStartLocal = $nowLocal->setTime(0, 0, 0);
        $dayEndLocal = $dayStartLocal->modify('+1 day');
        $dayStartUtc = $dayStartLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $dayEndUtc = $dayEndLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $nowUtcSql = $nowUtc->format('Y-m-d H:i:s');

        /** @var Lesson[] $lessons */
        $lessons = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['>=', 'starts_at', $dayStartUtc])
            ->andWhere(['<', 'starts_at', $dayEndUtc])
            ->with(['learner'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $teachable = array_values(array_filter(
            $lessons,
            static fn (Lesson $l) => in_array($l->status, [
                Lesson::STATUS_SCHEDULED,
                Lesson::STATUS_COMPLETED,
                Lesson::STATUS_NO_SHOW,
            ], true),
        ));

        if ($teachable === []) {
            return [
                'date' => $dayStartLocal->format('Y-m-d'),
                'date_display' => $dayStartLocal->format('l j F'),
                'timezone' => $org->timezone,
                'empty' => true,
                'day_complete' => false,
                'celebration' => null,
                'headline' => [
                    'lessons_line' => 'No lessons today',
                    'money_line' => null,
                    'open_admin_line' => null,
                ],
                'stats' => [],
                'totals' => [
                    'completed_count' => 0,
                    'no_show_count' => 0,
                    'still_open_count' => 0,
                    'teaching_minutes' => 0,
                    'taught_value_pence' => 0,
                    'outstanding_pence' => 0,
                    'open_admin_count' => 0,
                    'teaching_label' => '0h',
                    'taught_value_label' => Money::formatPence(0),
                    'outstanding_label' => Money::formatPence(0),
                ],
                'lessons' => [],
            ];
        }

        $lessonIds = array_map(static fn (Lesson $l) => (int) $l->id, $teachable);
        $learnerIds = array_values(array_unique(array_map(
            static fn (Lesson $l) => (int) $l->learner_id,
            $teachable,
        )));

        $progressLessonIds = $this->lessonIdsWithProgress($lessonIds);
        $futureLearnerIds = $this->learnerIdsWithFutureBooking($learnerIds, $nowUtcSql);
        $outstandingByLesson = $this->outstandingByLessonId($lessonIds);

        $rows = [];
        $completedCount = 0;
        $noShowCount = 0;
        $stillOpenCount = 0;
        $teachingMinutes = 0;
        $taughtValuePence = 0;
        $outstandingPence = 0;
        $openAdminCount = 0;

        foreach ($teachable as $lesson) {
            $lessonId = (int) $lesson->id;
            $learnerId = (int) $lesson->learner_id;
            $localStart = OrganisationTime::utcToLocal((string) $lesson->starts_at, $org);
            $endsUtc = (new DateTimeImmutable((string) $lesson->starts_at, new DateTimeZone('UTC')))
                ->modify('+' . (int) $lesson->duration_minutes . ' minutes');

            $isCompleted = $lesson->status === Lesson::STATUS_COMPLETED;
            $isNoShow = $lesson->status === Lesson::STATUS_NO_SHOW;
            $isStillOpen = $lesson->status === Lesson::STATUS_SCHEDULED;
            $isOverdue = $isStillOpen && $endsUtc <= $nowUtc;

            if ($isCompleted) {
                $completedCount++;
                $teachingMinutes += (int) $lesson->duration_minutes;
                $taughtValuePence += TeachingValueResolver::effectiveValuePence($lesson, $org);
            } elseif ($isNoShow) {
                $noShowCount++;
                $taughtValuePence += TeachingValueResolver::effectiveValuePence($lesson, $org);
            } elseif ($isStillOpen) {
                $stillOpenCount++;
            }

            $hasProgress = isset($progressLessonIds[$lessonId]);
            $hasNotes = $this->hasText($lesson->instructor_notes)
                || $this->hasText($lesson->learner_summary);
            $nextBooked = isset($futureLearnerIds[$learnerId]);

            $chargeOutstanding = (int) ($outstandingByLesson[$lessonId] ?? 0);
            $moneyOk = $this->moneyOk($lesson, $chargeOutstanding);
            if ($chargeOutstanding > 0) {
                $outstandingPence += $chargeOutstanding;
            }

            $checks = [
                'completed' => $isCompleted || $isNoShow,
                'still_open' => $isStillOpen,
                'overdue' => $isOverdue,
                'progress' => $isCompleted ? $hasProgress : null,
                'notes' => ($isCompleted || $isNoShow) ? $hasNotes : null,
                'next_booked' => $nextBooked,
                'money_ok' => ($isCompleted || $isNoShow) ? $moneyOk : null,
            ];

            $cta = $this->primaryCta($lesson, $checks, $chargeOutstanding);
            $needsAdmin = $cta !== null;
            if ($needsAdmin) {
                $openAdminCount++;
            }

            $statusLabel = match (true) {
                $isCompleted => 'Done',
                $isNoShow => 'No-show',
                $isOverdue => 'Still to complete',
                default => 'Scheduled',
            };

            $rows[] = [
                'id' => $lessonId,
                'learner_id' => $learnerId,
                'learner_name' => $lesson->learner?->fullName,
                'starts_at_time' => $localStart->format('H:i'),
                'duration_minutes' => (int) $lesson->duration_minutes,
                'status' => $lesson->status,
                'status_label' => $statusLabel,
                'checks' => $checks,
                'detail_line' => $this->detailLine($checks, $isCompleted, $isNoShow, $isStillOpen),
                'outstanding_pence' => $chargeOutstanding,
                'cta' => $cta,
            ];
        }

        $lessonsLine = $this->lessonsLine(
            count($teachable),
            $teachingMinutes,
            $completedCount,
            $stillOpenCount,
            $noShowCount,
        );
        $moneyLine = $this->moneyLine($taughtValuePence, $outstandingPence);
        $openAdminLine = $this->openAdminLine($openAdminCount);
        $teachingLabel = $this->hoursLabel($teachingMinutes);
        $dayComplete = $stillOpenCount === 0 && ($completedCount > 0 || $noShowCount > 0);

        return [
            'date' => $dayStartLocal->format('Y-m-d'),
            'date_display' => $dayStartLocal->format('l j F'),
            'timezone' => $org->timezone,
            'empty' => false,
            'day_complete' => $dayComplete,
            'celebration' => $this->celebration(
                $dayComplete,
                $completedCount,
                $noShowCount,
                $stillOpenCount,
                $openAdminCount,
                $teachingLabel,
            ),
            'headline' => [
                'lessons_line' => $lessonsLine,
                'money_line' => $moneyLine,
                'open_admin_line' => $openAdminLine,
            ],
            'stats' => $this->stats(
                $completedCount,
                $stillOpenCount,
                $teachingLabel,
                $taughtValuePence,
                $outstandingPence,
                $openAdminCount,
            ),
            'totals' => [
                'completed_count' => $completedCount,
                'no_show_count' => $noShowCount,
                'still_open_count' => $stillOpenCount,
                'teaching_minutes' => $teachingMinutes,
                'taught_value_pence' => $taughtValuePence,
                'outstanding_pence' => $outstandingPence,
                'open_admin_count' => $openAdminCount,
                'teaching_label' => $teachingLabel,
                'taught_value_label' => Money::formatPence($taughtValuePence),
                'outstanding_label' => Money::formatPence($outstandingPence),
            ],
            'lessons' => $rows,
        ];
    }

    /**
     * @return array{title: string, subtitle: string}|null
     */
    private function celebration(
        bool $dayComplete,
        int $completedCount,
        int $noShowCount,
        int $stillOpenCount,
        int $openAdminCount,
        string $teachingLabel,
    ): ?array {
        if ($dayComplete) {
            $lessonBit = $completedCount === 1
                ? '1 lesson done'
                : ($completedCount > 0 ? $completedCount . ' lessons done' : null);
            if ($lessonBit === null && $noShowCount > 0) {
                $lessonBit = $noShowCount === 1 ? '1 no-show logged' : $noShowCount . ' no-shows logged';
            }
            $subtitle = $lessonBit ?? 'Nothing left on the diary';
            if ($teachingLabel !== '0h' && $completedCount > 0) {
                $subtitle .= ' · ' . $teachingLabel . ' teaching';
            }
            if ($openAdminCount > 0) {
                $subtitle .= $openAdminCount === 1
                    ? ' · 1 still needs you'
                    : ' · ' . $openAdminCount . ' still need you';
            }

            return [
                'title' => 'Teaching day done',
                'subtitle' => $subtitle,
            ];
        }

        if ($stillOpenCount > 0 && $completedCount > 0) {
            return [
                'title' => 'Almost there',
                'subtitle' => $stillOpenCount === 1
                    ? '1 lesson still to finish'
                    : $stillOpenCount . ' lessons still to finish',
            ];
        }

        if ($stillOpenCount > 0) {
            return [
                'title' => 'Day still open',
                'subtitle' => $stillOpenCount === 1
                    ? '1 lesson still to complete'
                    : $stillOpenCount . ' lessons still to complete',
            ];
        }

        return null;
    }

    /**
     * @return list<array{key: string, label: string, value: string, tone: string}>
     */
    private function stats(
        int $completedCount,
        int $stillOpenCount,
        string $teachingLabel,
        int $taughtValuePence,
        int $outstandingPence,
        int $openAdminCount,
    ): array {
        return [
            [
                'key' => 'completed',
                'label' => 'Done',
                'value' => (string) $completedCount,
                'tone' => $completedCount > 0 ? 'good' : 'neutral',
            ],
            [
                'key' => 'teaching',
                'label' => 'Teaching',
                'value' => $teachingLabel,
                'tone' => 'neutral',
            ],
            [
                'key' => 'taught',
                'label' => 'Taught',
                'value' => Money::formatPence($taughtValuePence),
                'tone' => 'neutral',
            ],
            [
                'key' => 'collect',
                'label' => 'To collect',
                'value' => Money::formatPence($outstandingPence),
                'tone' => $outstandingPence > 0 ? 'warn' : 'good',
            ],
            [
                'key' => 'open',
                'label' => 'Still open',
                'value' => (string) $stillOpenCount,
                'tone' => $stillOpenCount > 0 ? 'warn' : 'good',
            ],
            [
                'key' => 'admin',
                'label' => 'Need you',
                'value' => (string) $openAdminCount,
                'tone' => $openAdminCount > 0 ? 'warn' : 'good',
            ],
        ];
    }

    /**
     * @param list<int> $lessonIds
     * @return array<int, true>
     */
    private function lessonIdsWithProgress(array $lessonIds): array
    {
        if ($lessonIds === []) {
            return [];
        }

        $skillLessonIds = TenantContext::scopeByOrganisation(LessonSkill::find())
            ->select(['lesson_id'])
            ->andWhere(['lesson_id' => $lessonIds])
            ->column();

        $progressLessonIds = TenantContext::scopeByOrganisation(
            \app\models\LearnerSkillProgress::find(),
        )
            ->select(['lesson_id'])
            ->andWhere(['lesson_id' => $lessonIds])
            ->andWhere(['not', ['lesson_id' => null]])
            ->column();

        $map = [];
        foreach (array_merge($skillLessonIds, $progressLessonIds) as $id) {
            $map[(int) $id] = true;
        }

        return $map;
    }

    /**
     * @param list<int> $learnerIds
     * @return array<int, true>
     */
    private function learnerIdsWithFutureBooking(array $learnerIds, string $nowUtcSql): array
    {
        if ($learnerIds === []) {
            return [];
        }

        $ids = TenantContext::scopeByOrganisation(Lesson::find())
            ->select(['learner_id'])
            ->andWhere([
                'learner_id' => $learnerIds,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>=', 'starts_at', $nowUtcSql])
            ->column();

        $map = [];
        foreach ($ids as $id) {
            $map[(int) $id] = true;
        }

        return $map;
    }

    /**
     * @param list<int> $lessonIds
     * @return array<int, int>
     */
    private function outstandingByLessonId(array $lessonIds): array
    {
        if ($lessonIds === []) {
            return [];
        }

        /** @var LessonCharge[] $charges */
        $charges = TenantContext::scopeByOrganisation(LessonCharge::find())
            ->andWhere(['lesson_id' => $lessonIds])
            ->andWhere(['status' => [LessonCharge::STATUS_OUTSTANDING, LessonCharge::STATUS_PAID]])
            ->all();

        $map = [];
        foreach ($charges as $charge) {
            $owed = $charge->outstandingPence();
            if ($owed > 0) {
                $map[(int) $charge->lesson_id] = ($map[(int) $charge->lesson_id] ?? 0) + $owed;
            }
        }

        return $map;
    }

    private function moneyOk(Lesson $lesson, int $chargeOutstanding): bool
    {
        if ($chargeOutstanding > 0) {
            return false;
        }

        // Settlement still marked outstanding even with no remaining charge — rare, but surface it.
        return $lesson->settlement !== FinanceService::SETTLEMENT_OUTSTANDING;
    }

    /**
     * @param array<string, mixed> $checks
     * @return array{label: string, path: string}|null
     */
    private function primaryCta(Lesson $lesson, array $checks, int $chargeOutstanding): ?array
    {
        $lessonId = (int) $lesson->id;
        $learnerId = (int) $lesson->learner_id;

        if (!empty($checks['still_open'])) {
            return [
                'label' => 'Open lesson',
                'path' => '/lessons/' . $lessonId,
            ];
        }

        if ($checks['progress'] === false) {
            return [
                'label' => 'Update progress',
                'path' => '/lessons/' . $lessonId,
            ];
        }

        if ($checks['money_ok'] === false || $chargeOutstanding > 0) {
            return [
                'label' => 'Record payment',
                'path' => '/pupils/' . $learnerId . '?pay=1',
            ];
        }

        if ($checks['next_booked'] === false && $lesson->status === Lesson::STATUS_COMPLETED) {
            return [
                'label' => 'Book next',
                'path' => '/lessons/new?learner_id=' . $learnerId . '&from_lesson=' . $lessonId,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $checks
     */
    private function detailLine(
        array $checks,
        bool $isCompleted,
        bool $isNoShow,
        bool $isStillOpen,
    ): string {
        if ($isStillOpen) {
            return !empty($checks['overdue']) ? 'Lesson not marked complete' : 'Still on the diary';
        }

        $parts = [];
        if ($isCompleted) {
            if ($checks['progress'] === true) {
                $parts[] = 'Progress updated';
            } elseif ($checks['progress'] === false) {
                $parts[] = 'Progress not updated';
            }
            if ($checks['notes'] === true) {
                $parts[] = 'Notes saved';
            }
        } elseif ($isNoShow) {
            $parts[] = 'No-show';
        }

        if ($checks['money_ok'] === false) {
            $parts[] = 'Money still owed';
        } elseif ($checks['money_ok'] === true && ($isCompleted || $isNoShow)) {
            $parts[] = 'Money settled';
        }

        if ($checks['next_booked'] === true) {
            $parts[] = 'Next booked';
        } elseif ($isCompleted) {
            $parts[] = 'No next lesson';
        }

        return $parts !== [] ? implode(' · ', $parts) : '';
    }

    private function lessonsLine(
        int $lessonCount,
        int $teachingMinutes,
        int $completedCount,
        int $stillOpenCount,
        int $noShowCount,
    ): string {
        $noun = $lessonCount === 1 ? 'lesson' : 'lessons';
        $parts = [$lessonCount . ' ' . $noun];

        if ($teachingMinutes > 0) {
            $parts[] = $this->hoursLabel($teachingMinutes) . ' teaching';
        }

        if ($stillOpenCount > 0) {
            $parts[] = $stillOpenCount . ' still to complete';
        } elseif ($noShowCount > 0 && $completedCount === 0) {
            $parts[] = $noShowCount === 1 ? '1 no-show' : $noShowCount . ' no-shows';
        }

        return implode(' · ', $parts);
    }

    private function moneyLine(int $taughtValuePence, int $outstandingPence): ?string
    {
        if ($taughtValuePence <= 0 && $outstandingPence <= 0) {
            return null;
        }

        $parts = [];
        if ($taughtValuePence > 0) {
            $parts[] = Money::formatPence($taughtValuePence) . ' taught';
        }
        if ($outstandingPence > 0) {
            $parts[] = Money::formatPence($outstandingPence) . ' still to collect';
        } elseif ($taughtValuePence > 0) {
            $parts[] = 'nothing left to collect from today';
        }

        return implode(' · ', $parts);
    }

    private function openAdminLine(int $openAdminCount): ?string
    {
        if ($openAdminCount <= 0) {
            return 'Admin clear for today';
        }

        return $openAdminCount === 1
            ? '1 still needs you'
            : $openAdminCount . ' still need you';
    }

    private function hoursLabel(int $minutes): string
    {
        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours . 'h';
        }
        if ($minutes < 60) {
            return $minutes . 'm';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours . 'h ' . $mins . 'm';
    }

    private function hasText(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    /**
     * @throws UnauthorizedHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }
}
