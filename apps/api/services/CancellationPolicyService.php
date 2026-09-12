<?php

declare(strict_types=1);

namespace app\services;

use app\models\Lesson;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Pupil self-cancel notice rules and learner-facing copy.
 *
 * Keeps charge decisions simple: enough notice → no charge;
 * short notice → either charge now or leave for the instructor.
 */
class CancellationPolicyService
{
    public const OUTCOME_FULL_RELEASE = 'full_release';
    public const OUTCOME_WILL_CHARGE = 'will_charge';
    public const OUTCOME_PENDING_DECISION = 'pending_decision';

    /**
     * @return array{
     *   allowed: bool,
     *   sufficient_notice: bool,
     *   notice_hours: int,
     *   hours_until: float,
     *   outcome: string,
     *   reason_required: bool,
     *   title: string,
     *   message: string,
     *   policy_text: string|null
     * }
     */
    public function previewForLearner(
        Lesson $lesson,
        Organisation $org,
        ?DateTimeImmutable $nowUtc = null,
    ): array {
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $allowed = $org->learnerCanCancel();
        $eval = $this->evaluate($lesson, $org, $nowUtc);

        return [
            'allowed' => $allowed,
            'sufficient_notice' => $eval['sufficient_notice'],
            'notice_hours' => $eval['notice_hours'],
            'hours_until' => $eval['hours_until'],
            'outcome' => $eval['outcome'],
            'reason_required' => $allowed && !$eval['sufficient_notice'],
            'title' => $this->learnerTitle($eval),
            'message' => $this->learnerMessage($eval),
            'policy_text' => $this->policyText($org),
        ];
    }

    /**
     * @return array{
     *   sufficient_notice: bool,
     *   notice_hours: int,
     *   hours_until: float,
     *   outcome: string
     * }
     */
    public function evaluate(
        Lesson $lesson,
        Organisation $org,
        ?DateTimeImmutable $nowUtc = null,
    ): array {
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $starts = new DateTimeImmutable((string) $lesson->starts_at, new DateTimeZone('UTC'));
        $hoursUntil = ($starts->getTimestamp() - $nowUtc->getTimestamp()) / 3600;
        $noticeHours = $org->cancellationNoticeHours();
        $sufficient = $hoursUntil >= $noticeHours;

        if ($sufficient) {
            $outcome = self::OUTCOME_FULL_RELEASE;
        } elseif ($org->cancellationLatePolicy() === Organisation::CANCELLATION_LATE_CHARGE) {
            $outcome = self::OUTCOME_WILL_CHARGE;
        } else {
            $outcome = self::OUTCOME_PENDING_DECISION;
        }

        return [
            'sufficient_notice' => $sufficient,
            'notice_hours' => $noticeHours,
            'hours_until' => round($hoursUntil, 2),
            'outcome' => $outcome,
        ];
    }

    /**
     * Finance payload for portal cancel, or null when instructor decides later.
     *
     * @return array{charge: string}|null
     */
    public function settlePayloadForOutcome(string $outcome): ?array
    {
        return match ($outcome) {
            self::OUTCOME_FULL_RELEASE => ['charge' => 'waived'],
            self::OUTCOME_WILL_CHARGE => ['charge' => 'outstanding'],
            default => null,
        };
    }

    /**
     * Persistable notice hours from a cancel evaluation (portal self-cancel).
     *
     * @param array{hours_until: float} $eval
     */
    public function noticeHoursFromEvaluation(array $eval): int
    {
        return max(0, (int) round((float) $eval['hours_until']));
    }

    /**
     * Optional notice hours from an instructor cancel payload.
     *
     * @param array<string, mixed> $data
     */
    public function noticeHoursFromPayload(array $data): ?int
    {
        if (!array_key_exists('cancellation_notice_hours', $data) && !array_key_exists('notice_hours', $data)) {
            return null;
        }
        $raw = $data['cancellation_notice_hours'] ?? $data['notice_hours'];
        if ($raw === null || $raw === '') {
            return null;
        }
        $hours = (int) $raw;
        if ($hours < 0 || $hours > 720) {
            throw new \yii\web\BadRequestHttpException('Notice must be between 0 and 720 hours.');
        }

        return $hours;
    }

    public function formatNoticeLabel(?int $hours): ?string
    {
        if ($hours === null) {
            return null;
        }
        if ($hours < 1) {
            return 'less than an hour’s notice';
        }
        if ($hours === 1) {
            return '1 hour’s notice';
        }
        if ($hours < 24) {
            return $hours . ' hours’ notice';
        }
        $days = intdiv($hours, 24);
        $remainder = $hours % 24;
        if ($remainder === 0) {
            return $days === 1 ? '1 day’s notice' : $days . ' days’ notice';
        }

        return $hours . ' hours’ notice';
    }

    /**
     * @param array{sufficient_notice: bool, notice_hours: int, outcome: string} $eval
     */
    private function learnerTitle(array $eval): string
    {
        if ($eval['sufficient_notice']) {
            return 'Cancel this lesson?';
        }

        return 'Cancel with less than ' . $eval['notice_hours'] . ' hours’ notice?';
    }

    /**
     * @param array{sufficient_notice: bool, notice_hours: int, outcome: string} $eval
     */
    private function learnerMessage(array $eval): string
    {
        if ($eval['outcome'] === self::OUTCOME_FULL_RELEASE) {
            return 'You won’t be charged. Please only cancel if you need to.';
        }
        if ($eval['outcome'] === self::OUTCOME_WILL_CHARGE) {
            return 'You’ll still be charged for this lesson because there isn’t enough notice.';
        }

        return 'Your instructor may still charge for this lesson because there isn’t enough notice.';
    }

    private function policyText(Organisation $org): ?string
    {
        $text = trim((string) ($org->cancellation_policy ?? ''));

        return $text === '' ? null : $text;
    }
}
