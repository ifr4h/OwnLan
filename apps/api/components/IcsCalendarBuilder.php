<?php

declare(strict_types=1);

namespace app\components;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Minimal iCalendar (RFC 5545) builder for lesson feeds.
 */
final class IcsCalendarBuilder
{
    private const PROD_ID = '-//OwnLane//Lesson Calendar//EN';

    /**
     * @param list<array{
     *   uid: string,
     *   summary: string,
     *   description: string,
     *   location: string,
     *   start: DateTimeImmutable,
     *   end: DateTimeImmutable,
     *   cancelled: bool,
     *   last_modified: DateTimeImmutable,
     * }> $events
     */
    public static function build(string $calendarName, array $events, DateTimeZone $tz): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PROD_ID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escapeText($calendarName),
            'X-WR-TIMEZONE:' . self::escapeText($tz->getName()),
        ];

        foreach ($events as $event) {
            $lines = array_merge($lines, self::eventLines($event, $tz));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param array{
     *   uid: string,
     *   summary: string,
     *   description: string,
     *   location: string,
     *   start: DateTimeImmutable,
     *   end: DateTimeImmutable,
     *   cancelled: bool,
     *   last_modified: DateTimeImmutable,
     * } $event
     * @return list<string>
     */
    private static function eventLines(array $event, DateTimeZone $tz): array
    {
        $start = $event['start']->setTimezone($tz);
        $end = $event['end']->setTimezone($tz);
        $modified = $event['last_modified']->setTimezone(new DateTimeZone('UTC'));

        $lines = [
            'BEGIN:VEVENT',
            'UID:' . self::escapeText($event['uid']),
            'DTSTAMP:' . self::formatUtc($modified),
            'LAST-MODIFIED:' . self::formatUtc($modified),
            'DTSTART;TZID=' . $tz->getName() . ':' . self::formatLocal($start),
            'DTEND;TZID=' . $tz->getName() . ':' . self::formatLocal($end),
            'SUMMARY:' . self::escapeText($event['summary']),
        ];

        if ($event['description'] !== '') {
            $lines[] = 'DESCRIPTION:' . self::escapeText($event['description']);
        }
        if ($event['location'] !== '') {
            $lines[] = 'LOCATION:' . self::escapeText($event['location']);
        }
        if ($event['cancelled']) {
            $lines[] = 'STATUS:CANCELLED';
        } else {
            $lines[] = 'STATUS:CONFIRMED';
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    public static function lessonUid(int $lessonId): string
    {
        return 'ownlane-lesson-' . $lessonId . '@ownlane.app';
    }

    private static function formatUtc(DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private static function formatLocal(DateTimeImmutable $dt): string
    {
        return $dt->format('Ymd\THis');
    }

    private static function escapeText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace("\r\n", '\n', $value);
        $value = str_replace("\n", '\n', $value);

        return $value;
    }
}
