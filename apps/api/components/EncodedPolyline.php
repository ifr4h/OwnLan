<?php

declare(strict_types=1);

namespace app\components;

/**
 * Google Encoded Polyline Algorithm Format — compact route geometry without GIS infra.
 *
 * @see https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final class EncodedPolyline
{
    /**
     * @param list<array{0: float, 1: float}> $points [[lat, lng], ...]
     */
    public static function encode(array $points): string
    {
        $result = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $point) {
            $lat = (int) round($point[0] * 1e5);
            $lng = (int) round($point[1] * 1e5);
            $result .= self::encodeSigned($lat - $prevLat);
            $result .= self::encodeSigned($lng - $prevLng);
            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $result;
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public static function decode(string $encoded): array
    {
        $length = strlen($encoded);
        $index = 0;
        $lat = 0;
        $lng = 0;
        $points = [];

        while ($index < $length) {
            $lat += self::decodeSigned($encoded, $index);
            $lng += self::decodeSigned($encoded, $index);
            $points[] = [$lat / 1e5, $lng / 1e5];
        }

        return $points;
    }

    /**
     * Approximate path length in metres (haversine between consecutive points).
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function approximateDistanceMetres(array $points): int
    {
        if (count($points) < 2) {
            return 0;
        }

        $metres = 0.0;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $metres += self::haversineMetres(
                $points[$i - 1][0],
                $points[$i - 1][1],
                $points[$i][0],
                $points[$i][1],
            );
        }

        return (int) round($metres);
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @return array{north: float, south: float, east: float, west: float}|null
     */
    public static function bounds(array $points): ?array
    {
        if ($points === []) {
            return null;
        }

        $north = $south = $points[0][0];
        $east = $west = $points[0][1];
        foreach ($points as [$lat, $lng]) {
            $north = max($north, $lat);
            $south = min($south, $lat);
            $east = max($east, $lng);
            $west = min($west, $lng);
        }

        return [
            'north' => $north,
            'south' => $south,
            'east' => $east,
            'west' => $west,
        ];
    }

    private static function encodeSigned(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $result = '';
        while ($value >= 0x20) {
            $result .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }
        $result .= chr($value + 63);

        return $result;
    }

    private static function decodeSigned(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;
        do {
            $b = ord($encoded[$index++]) - 63;
            $result |= ($b & 0x1f) << $shift;
            $shift += 5;
        } while ($b >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }

    private static function haversineMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);
        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }
}
