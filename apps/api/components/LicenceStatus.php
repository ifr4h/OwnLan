<?php

declare(strict_types=1);

namespace app\components;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Provisional / photocard licence expiry urgency for instructor UI.
 */
final class LicenceStatus
{
    /**
     * @return array<string, mixed>|null
     */
    public static function statusPayload(
        ?string $licenceNumber,
        ?string $expiryYmd,
        ?DateTimeImmutable $now = null,
    ): ?array {
        $number = $licenceNumber !== null ? trim($licenceNumber) : '';
        $expiryRaw = $expiryYmd !== null ? trim($expiryYmd) : '';

        if ($number === '' && $expiryRaw === '') {
            return null;
        }

        $now = $now?->setTimezone(new DateTimeZone('UTC'))
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $payload = [
            'number' => $number !== '' ? $number : null,
            'expires_on' => null,
            'expires_on_display' => null,
            'days_until_expiry' => null,
            'label' => $number !== '' ? 'Licence on file' : 'Licence',
            'urgency' => 'none',
        ];

        if ($expiryRaw === '') {
            return $payload;
        }

        $expiry = DateTimeImmutable::createFromFormat('Y-m-d', $expiryRaw, new DateTimeZone('UTC'));
        if ($expiry === false || $expiry->format('Y-m-d') !== $expiryRaw) {
            return $payload;
        }

        $days = (int) floor(
            ($expiry->setTime(0, 0)->getTimestamp() - $now->setTime(0, 0)->getTimestamp()) / 86400,
        );
        $payload['expires_on'] = $expiryRaw;
        $payload['expires_on_display'] = $expiry->format('j M Y');
        $payload['days_until_expiry'] = $days;

        if ($days < 0) {
            $payload['label'] = 'Licence expired';
            $payload['urgency'] = 'expired';
        } elseif ($days <= 28) {
            $payload['label'] = 'Licence expires in ' . $days . ' ' . ($days === 1 ? 'day' : 'days');
            $payload['urgency'] = 'soon';
        } elseif ($days <= 84) {
            $weeks = (int) ceil($days / 7);
            $payload['label'] = 'Licence expires in ' . $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks');
            $payload['urgency'] = 'approaching';
        } else {
            $payload['label'] = 'Licence valid until ' . $payload['expires_on_display'];
            $payload['urgency'] = 'ok';
        }

        return $payload;
    }
}
