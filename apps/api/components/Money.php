<?php

declare(strict_types=1);

namespace app\components;

use InvalidArgumentException;

/**
 * Exact sterling amounts as integer pence. Never float.
 */
final class Money
{
    public static function formatPence(int $pence): string
    {
        $sign = $pence < 0 ? '−' : '';
        $abs = abs($pence);
        $pounds = intdiv($abs, 100);
        $remainder = $abs % 100;

        return $sign . '£' . number_format($pounds, 0, '.', ',') . '.' . str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Parse instructor-entered pounds (string or int) to pence.
     * Accepts "40", "40.00", "40.5", "1,250.00". Rejects floats as input type.
     */
    public static function poundsToPence(mixed $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            throw new InvalidArgumentException('Money must not use floating point. Pass pence or a pounds string.');
        }
        if (!is_string($value) && !is_numeric($value)) {
            throw new InvalidArgumentException('Amount is required.');
        }

        $raw = trim((string) $value);
        $raw = str_replace([',', '£', ' '], '', $raw);
        if ($raw === '' || !preg_match('/^-?\d+(\.\d{1,2})?$/', $raw)) {
            throw new InvalidArgumentException('Enter an amount like 40 or 40.50.');
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }

        if (str_contains($raw, '.')) {
            [$poundsPart, $fraction] = explode('.', $raw, 2);
            $fraction = str_pad($fraction, 2, '0');
            $pence = ((int) $poundsPart) * 100 + (int) $fraction;
        } else {
            $pence = ((int) $raw) * 100;
        }

        return $negative ? -$pence : $pence;
    }

    public static function requireNonNegativePence(mixed $value, string $label = 'Amount'): int
    {
        if (is_float($value)) {
            throw new InvalidArgumentException("{$label} must not use floating point. Use whole pence.");
        }
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', trim($value)))) {
            throw new InvalidArgumentException("{$label} must be whole pence (integer).");
        }
        $pence = (int) $value;
        if ($pence < 0) {
            throw new InvalidArgumentException("{$label} cannot be negative.");
        }

        return $pence;
    }

    /** Price for a lesson duration from an hourly rate, integer division. */
    public static function lessonPriceFromHourlyRate(int $hourlyRatePence, int $durationMinutes): int
    {
        if ($hourlyRatePence < 0 || $durationMinutes < 0) {
            throw new InvalidArgumentException('Rate and duration must be non-negative.');
        }

        return intdiv($hourlyRatePence * $durationMinutes, 60);
    }
}
