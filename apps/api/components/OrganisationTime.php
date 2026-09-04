<?php

declare(strict_types=1);

namespace app\components;

use app\models\Organisation;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use yii\web\BadRequestHttpException;

/**
 * Convert between organisation-local wall times and UTC storage.
 */
final class OrganisationTime
{
    /**
     * @throws BadRequestHttpException
     */
    public static function timezoneFor(Organisation $organisation): DateTimeZone
    {
        $name = $organisation->timezone ?: 'Europe/London';
        try {
            return new DateTimeZone($name);
        } catch (\Exception) {
            throw new BadRequestHttpException('Organisation timezone is invalid.');
        }
    }

    /**
     * Parse a local wall time (no offset) in the organisation timezone → UTC.
     *
     * Accepts "Y-m-d H:i", "Y-m-d H:i:s", or "Y-m-d\TH:i".
     *
     * @throws BadRequestHttpException
     */
    public static function localToUtc(string $local, Organisation $organisation): DateTimeImmutable
    {
        $local = trim(str_replace('T', ' ', $local));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $local)) {
            $local .= ':00';
        }

        $tz = self::timezoneFor($organisation);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $local, $tz);
        $errors = DateTimeImmutable::getLastErrors();
        $hasParseIssues = is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if ($dt === false || $hasParseIssues) {
            throw new BadRequestHttpException('Enter a valid date and time.');
        }

        return $dt->setTimezone(new DateTimeZone('UTC'));
    }

    public static function utcToLocal(DateTimeInterface|string $utc, Organisation $organisation): DateTimeImmutable
    {
        if ($utc instanceof DateTimeInterface) {
            $dt = DateTimeImmutable::createFromInterface($utc)->setTimezone(new DateTimeZone('UTC'));
        } else {
            $dt = new DateTimeImmutable((string) $utc, new DateTimeZone('UTC'));
        }

        return $dt->setTimezone(self::timezoneFor($organisation));
    }

    public static function formatUtc(DateTimeInterface $utc): string
    {
        return $utc->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function formatLocalIso(DateTimeInterface $local): string
    {
        return $local->format('Y-m-d\TH:i');
    }

    public static function formatLocalDisplay(DateTimeInterface $local): string
    {
        return $local->format('D j M Y · H:i');
    }
}
