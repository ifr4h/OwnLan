<?php

declare(strict_types=1);

namespace app\travel;

/**
 * Offline-friendly V1 estimate — no external maps API.
 *
 * Uses UK postcode comparison when present; otherwise a conservative default
 * for distinct addresses. Replace via TravelTimeProvider later (Google/Mapbox).
 */
final class HeuristicTravelProvider implements TravelTimeProvider
{
    public const SAME_ADDRESS_MINUTES = 0;
    public const SAME_POSTCODE_MINUTES = 5;
    public const SAME_DISTRICT_MINUTES = 12;
    public const DEFAULT_DISTINCT_MINUTES = 22;

    public function estimateDriveMinutes(?string $fromAddress, ?string $toAddress): ?int
    {
        $from = $this->normalize($fromAddress);
        $to = $this->normalize($toAddress);
        if ($from === null || $to === null) {
            return null;
        }
        if ($from === $to) {
            return self::SAME_ADDRESS_MINUTES;
        }

        $fromPc = $this->extractPostcode($from);
        $toPc = $this->extractPostcode($to);
        if ($fromPc !== null && $toPc !== null) {
            if ($fromPc === $toPc) {
                return self::SAME_POSTCODE_MINUTES;
            }
            if ($this->outwardCode($fromPc) === $this->outwardCode($toPc)) {
                return self::SAME_DISTRICT_MINUTES;
            }
        }

        return self::DEFAULT_DISTINCT_MINUTES;
    }

    private function normalize(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $address) ?? ''));
        if ($text === '') {
            return null;
        }

        return $text;
    }

    private function extractPostcode(string $normalized): ?string
    {
        if (preg_match('/\b([a-z]{1,2}\d[a-z\d]?\s*\d[a-z]{2})\b/', $normalized, $m) !== 1) {
            return null;
        }

        return strtoupper(preg_replace('/\s+/', '', $m[1]) ?? $m[1]);
    }

    private function outwardCode(string $compactPostcode): string
    {
        // e.g. SW1A1AA → SW1A
        return substr($compactPostcode, 0, -3);
    }
}
