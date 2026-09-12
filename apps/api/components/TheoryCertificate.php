<?php

declare(strict_types=1);

namespace app\components;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * UK theory test certificate validity helpers.
 *
 * DVSA: a theory test pass certificate is normally valid for 2 years from the
 * date of the test. OwnLane derives expiry rather than asking learners to calculate it.
 *
 * @see https://www.gov.uk/theory-test/pass-mark-and-certificate
 */
final class TheoryCertificate
{
    public const VALIDITY_YEARS = 2;

    public static function expiryDate(string $passDateYmd): ?DateTimeImmutable
    {
        $pass = DateTimeImmutable::createFromFormat('Y-m-d', $passDateYmd, new DateTimeZone('UTC'));
        if ($pass === false || $pass->format('Y-m-d') !== $passDateYmd) {
            return null;
        }

        return $pass->add(new DateInterval('P' . self::VALIDITY_YEARS . 'Y'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function statusPayload(
        ?string $theoryStatus,
        ?string $passDateYmd = null,
        ?DateTimeImmutable $now = null,
        ?string $bookedTestDateYmd = null,
    ): ?array {
        if ($theoryStatus === null || $theoryStatus === '') {
            return null;
        }

        $now = $now?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $today = $now->setTime(0, 0);

        $payload = [
            'status' => $theoryStatus,
            'pass_date' => null,
            'test_date' => null,
            'test_date_display' => null,
            'days_until_test' => null,
            'expires_on' => null,
            'expires_on_display' => null,
            'days_until_expiry' => null,
            'label' => match ($theoryStatus) {
                'passed' => 'Theory passed',
                'booked' => 'Theory booked',
                'not_yet' => 'Not passed yet',
                default => 'Theory',
            },
            'detail' => null,
            'urgency' => 'none',
        ];

        if ($theoryStatus === 'not_yet') {
            $payload['detail'] = 'Still needs to book and pass the theory test.';
            $payload['urgency'] = 'none';

            return $payload;
        }

        if ($theoryStatus === 'booked') {
            return self::withBookedDetails($payload, $bookedTestDateYmd, $today);
        }

        if ($theoryStatus === 'passed' && $passDateYmd) {
            return self::withPassDetails($payload, $passDateYmd, $today);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function withBookedDetails(array $payload, ?string $bookedTestDateYmd, DateTimeImmutable $today): array
    {
        if ($bookedTestDateYmd === null || $bookedTestDateYmd === '') {
            $payload['label'] = 'Theory date missing';
            $payload['detail'] = 'Marked as booked — add the test date.';
            $payload['urgency'] = 'soon';

            return $payload;
        }

        $test = DateTimeImmutable::createFromFormat('Y-m-d', $bookedTestDateYmd, new DateTimeZone('UTC'));
        if ($test === false || $test->format('Y-m-d') !== $bookedTestDateYmd) {
            $payload['label'] = 'Theory date missing';
            $payload['detail'] = 'Marked as booked — add the test date.';
            $payload['urgency'] = 'soon';

            return $payload;
        }

        $test = $test->setTime(0, 0);
        $days = (int) floor(($test->getTimestamp() - $today->getTimestamp()) / 86400);
        $display = $test->format('j F Y');

        $payload['test_date'] = $bookedTestDateYmd;
        $payload['test_date_display'] = $display;
        $payload['days_until_test'] = $days;

        if ($days < 0) {
            $payload['label'] = 'Theory date passed';
            $payload['detail'] = 'Was booked for ' . $display . '. Update if they sat it.';
            $payload['urgency'] = 'expired';
        } elseif ($days === 0) {
            $payload['label'] = 'Theory today';
            $payload['detail'] = 'Booked for today.';
            $payload['urgency'] = 'soon';
        } elseif ($days === 1) {
            $payload['label'] = 'Theory tomorrow';
            $payload['detail'] = 'Booked for ' . $display . '.';
            $payload['urgency'] = 'soon';
        } elseif ($days <= 14) {
            $payload['label'] = 'Theory in ' . $days . ' days';
            $payload['detail'] = 'Booked for ' . $display . '.';
            $payload['urgency'] = 'soon';
        } elseif ($days <= 42) {
            $weeks = (int) ceil($days / 7);
            $payload['label'] = 'Theory in ' . $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks');
            $payload['detail'] = 'Booked for ' . $display . '.';
            $payload['urgency'] = 'approaching';
        } else {
            $payload['label'] = 'Theory booked';
            $payload['detail'] = 'Booked for ' . $display . ' · ' . $days . ' days left.';
            $payload['urgency'] = 'ok';
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function withPassDetails(array $payload, string $passDateYmd, DateTimeImmutable $today): array
    {
        $expiry = self::expiryDate($passDateYmd);
        if ($expiry === null) {
            return $payload;
        }

        $days = (int) floor(($expiry->setTime(0, 0)->getTimestamp() - $today->getTimestamp()) / 86400);
        $payload['pass_date'] = $passDateYmd;
        $payload['expires_on'] = $expiry->format('Y-m-d');
        $payload['expires_on_display'] = $expiry->format('j F Y');
        $payload['days_until_expiry'] = $days;

        if ($days < 0) {
            $payload['label'] = 'Theory expired';
            $payload['detail'] = 'Certificate ran out on ' . $payload['expires_on_display'] . '. They need to retake theory.';
            $payload['urgency'] = 'expired';
        } elseif ($days <= 28) {
            $payload['label'] = $days . ' ' . ($days === 1 ? 'day' : 'days') . ' left on theory';
            $payload['detail'] = 'Valid until ' . $payload['expires_on_display'] . '. Practical needs booking before then.';
            $payload['urgency'] = 'soon';
        } elseif ($days <= 84) {
            $weeks = (int) ceil($days / 7);
            $payload['label'] = $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks') . ' left on theory';
            $payload['detail'] = 'Valid until ' . $payload['expires_on_display'] . '.';
            $payload['urgency'] = 'approaching';
        } else {
            $months = (int) floor($days / 30);
            $payload['label'] = 'Theory passed';
            $payload['detail'] = 'Valid until ' . $payload['expires_on_display']
                . ($months >= 2 ? ' · about ' . $months . ' months left to take the practical.' : '.');
            $payload['urgency'] = 'ok';
        }

        return $payload;
    }
}
