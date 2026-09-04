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
    public static function statusPayload(?string $theoryStatus, ?string $passDateYmd, ?DateTimeImmutable $now = null): ?array
    {
        if ($theoryStatus === null || $theoryStatus === '') {
            return null;
        }

        $now = $now?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $payload = [
            'status' => $theoryStatus,
            'pass_date' => null,
            'expires_on' => null,
            'expires_on_display' => null,
            'days_until_expiry' => null,
            'label' => match ($theoryStatus) {
                'passed' => 'Theory passed',
                'booked' => 'Theory booked',
                'not_yet' => 'Theory not passed yet',
                default => 'Theory',
            },
            'urgency' => 'none',
        ];

        if ($theoryStatus === 'passed' && $passDateYmd) {
            $expiry = self::expiryDate($passDateYmd);
            if ($expiry !== null) {
                $days = (int) floor(($expiry->setTime(0, 0)->getTimestamp() - $now->setTime(0, 0)->getTimestamp()) / 86400);
                $payload['pass_date'] = $passDateYmd;
                $payload['expires_on'] = $expiry->format('Y-m-d');
                $payload['expires_on_display'] = $expiry->format('j F Y');
                $payload['days_until_expiry'] = $days;
                if ($days < 0) {
                    $payload['label'] = 'Theory expired';
                    $payload['urgency'] = 'expired';
                } elseif ($days <= 28) {
                    $payload['label'] = 'Theory expires in ' . $days . ' ' . ($days === 1 ? 'day' : 'days');
                    $payload['urgency'] = 'soon';
                } elseif ($days <= 84) {
                    $weeks = (int) ceil($days / 7);
                    $payload['label'] = 'Theory expires in ' . $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks');
                    $payload['urgency'] = 'approaching';
                } else {
                    $payload['label'] = 'Theory passed · valid until ' . $payload['expires_on_display'];
                    $payload['urgency'] = 'ok';
                }
            }
        }

        return $payload;
    }
}
