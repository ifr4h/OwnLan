<?php

declare(strict_types=1);

namespace app\services;

use Yii;

/**
 * Best-effort lesson-hour weather for Today (Open-Meteo + UK postcodes.io).
 * Soft-fails: never blocks the teaching day if remote lookups fail.
 */
class WeatherService
{
    private const WINDY_KMH = 40.0;
    private const CACHE_TTL = 1800; // 30 minutes
    private const HTTP_TIMEOUT = 5;

    /**
     * @param list<array<string, mixed>> $lessons Today-serialized lessons
     * @return list<array<string, mixed>>
     */
    public function annotateLessons(array $lessons, string $date, string $timezone): array
    {
        if ($lessons === []) {
            return $lessons;
        }

        if (defined('YII_ENV') && YII_ENV === 'test') {
            return $lessons;
        }

        $addresses = [];
        foreach ($lessons as $lesson) {
            $addr = isset($lesson['pickup_address']) ? trim((string) $lesson['pickup_address']) : '';
            if ($addr !== '') {
                $addresses[] = $addr;
            }
        }

        $point = $this->resolvePoint($addresses);
        if ($point === null) {
            return $lessons;
        }

        $hourly = $this->fetchHourly($point['lat'], $point['lng'], $date, $timezone);
        if ($hourly === null) {
            return $lessons;
        }

        foreach ($lessons as $i => $lesson) {
            $local = (string) ($lesson['starts_at_local'] ?? $lesson['starts_at'] ?? '');
            $snap = $this->snapshotForLocalStart($hourly, $local);
            if ($snap !== null) {
                $lessons[$i]['weather'] = $snap;
            }
        }

        return $lessons;
    }

    /**
     * @param list<string> $addresses
     * @return array{lat: float, lng: float}|null
     */
    private function resolvePoint(array $addresses): ?array
    {
        foreach ($addresses as $address) {
            $point = $this->geocodeAddress($address);
            if ($point !== null) {
                return $point;
            }
        }

        // Central England fallback so UK teaching days still get a chip.
        return ['lat' => 52.4862, 'lng' => -1.8904];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function geocodeAddress(string $address): ?array
    {
        $postcode = $this->extractUkPostcode($address);
        if ($postcode !== null) {
            $fromPostcode = $this->geocodeUkPostcode($postcode);
            if ($fromPostcode !== null) {
                return $fromPostcode;
            }
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        $candidates = [];
        foreach (array_reverse($parts) as $part) {
            if (!preg_match('/^\d+\s/', $part)) {
                $candidates[] = $part;
            }
        }
        foreach ($parts as $part) {
            $candidates[] = $part;
        }

        $seen = [];
        foreach ($candidates as $name) {
            $key = strtolower($name);
            if (isset($seen[$key]) || strlen($name) < 3) {
                continue;
            }
            $seen[$key] = true;
            $hit = $this->geocodePlaceName($name);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    public function extractUkPostcode(string $address): ?string
    {
        if (!preg_match('/\b([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})\b/i', $address, $m)) {
            return null;
        }

        return strtoupper(preg_replace('/\s+/', ' ', $m[1]) ?? $m[1]);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function geocodeUkPostcode(string $postcode): ?array
    {
        $compact = preg_replace('/\s+/', '', $postcode) ?? $postcode;
        $cacheKey = 'weather.postcode.' . strtoupper($compact);
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return $cached;
        }

        $url = 'https://api.postcodes.io/postcodes/' . rawurlencode($compact);
        $data = $this->httpJson($url);
        if ($data === null || !isset($data['result']['latitude'], $data['result']['longitude'])) {
            return null;
        }

        $point = [
            'lat' => (float) $data['result']['latitude'],
            'lng' => (float) $data['result']['longitude'],
        ];
        Yii::$app->cache->set($cacheKey, $point, 86400);

        return $point;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function geocodePlaceName(string $name): ?array
    {
        $url = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
            'name' => $name,
            'count' => 1,
            'language' => 'en',
            'countryCode' => 'GB',
        ]);
        $data = $this->httpJson($url);
        if ($data === null || empty($data['results'][0]['latitude']) || empty($data['results'][0]['longitude'])) {
            return null;
        }

        return [
            'lat' => (float) $data['results'][0]['latitude'],
            'lng' => (float) $data['results'][0]['longitude'],
        ];
    }

    /**
     * @return array{
     *   time: list<string>,
     *   weather_code: list<int|float>,
     *   temperature_2m: list<int|float>,
     *   precipitation_probability: list<int|float>,
     *   wind_speed_10m: list<int|float>
     * }|null
     */
    private function fetchHourly(float $lat, float $lng, string $date, string $timezone): ?array
    {
        $cacheKey = sprintf('weather.hourly.%s.%.2f.%.2f', $date, $lat, $lng);
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached) && isset($cached['time']) && is_array($cached['time']) && $cached['time'] !== []) {
            return $cached;
        }

        $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
            'latitude' => $lat,
            'longitude' => $lng,
            'hourly' => 'weather_code,temperature_2m,precipitation_probability,wind_speed_10m',
            'timezone' => $timezone !== '' ? $timezone : 'Europe/London',
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $data = $this->httpJson($url);
        if ($data === null || empty($data['hourly']['time']) || !is_array($data['hourly']['time'])) {
            Yii::warning('Weather hourly fetch failed for ' . $date . ' @ ' . $lat . ',' . $lng, __METHOD__);
            return null;
        }

        /** @var array{time: list<string>, weather_code: list<int|float>, temperature_2m: list<int|float>, precipitation_probability: list<int|float>, wind_speed_10m: list<int|float>} $hourly */
        $hourly = $data['hourly'];
        Yii::$app->cache->set($cacheKey, $hourly, self::CACHE_TTL);

        return $hourly;
    }

    /**
     * @param array{
     *   time: list<string>,
     *   weather_code: list<int|float>,
     *   temperature_2m: list<int|float>,
     *   precipitation_probability: list<int|float>,
     *   wind_speed_10m: list<int|float>
     * } $hourly
     * @return array<string, mixed>|null
     */
    private function snapshotForLocalStart(array $hourly, string $startsAtLocal): ?array
    {
        $normalised = str_replace(' ', 'T', $startsAtLocal);
        $hourPrefix = substr($normalised, 0, 13);
        if (strlen($hourPrefix) < 13) {
            return null;
        }

        $idx = null;
        foreach ($hourly['time'] as $i => $time) {
            $t = str_replace(' ', 'T', (string) $time);
            if ($t === $hourPrefix . ':00' || str_starts_with($t, $hourPrefix)) {
                $idx = $i;
                break;
            }
        }

        if ($idx === null) {
            return null;
        }

        $code = (int) ($hourly['weather_code'][$idx] ?? 3);
        $wind = (float) ($hourly['wind_speed_10m'][$idx] ?? 0);
        $windy = $wind >= self::WINDY_KMH;
        $kind = $this->kindFromCode($code);
        if ($windy && $kind === 'clear' && $wind >= 55) {
            $kind = 'windy';
        }

        $label = $this->labelFor($kind, $windy);
        $temp = isset($hourly['temperature_2m'][$idx]) ? (float) $hourly['temperature_2m'][$idx] : null;
        $hint = 'During this lesson, it might be ' . $label;
        if ($temp !== null && is_finite($temp)) {
            $hint .= ', around ' . (string) (int) round($temp) . '°C';
        }
        $hint .= '.';

        return [
            'kind' => $kind,
            'label' => $label,
            'hint' => $hint,
            'temperature_c' => $temp,
            'precipitation_probability' => isset($hourly['precipitation_probability'][$idx])
                ? (float) $hourly['precipitation_probability'][$idx]
                : null,
            'windy' => $windy,
            'hour' => (string) ($hourly['time'][$idx] ?? $hourPrefix),
        ];
    }

    private function kindFromCode(int $code): string
    {
        if ($code === 0) {
            return 'clear';
        }
        if ($code <= 2) {
            return 'partly_cloudy';
        }
        if ($code === 3) {
            return 'cloudy';
        }
        if ($code === 45 || $code === 48) {
            return 'fog';
        }
        if ($code >= 51 && $code <= 57) {
            return 'drizzle';
        }
        if ($code === 61 || $code === 80) {
            return 'light_rain';
        }
        if ($code === 63 || $code === 81) {
            return 'rain';
        }
        if ($code === 65 || $code === 82) {
            return 'heavy_rain';
        }
        if (
            ($code >= 66 && $code <= 67)
            || ($code >= 71 && $code <= 77)
            || $code === 85
            || $code === 86
        ) {
            return 'wintry';
        }
        if ($code >= 95) {
            return 'thunder';
        }

        return 'cloudy';
    }

    private function labelFor(string $kind, bool $windy): string
    {
        $base = match ($kind) {
            'clear' => 'bright sunshine',
            'partly_cloudy' => 'partly cloudy',
            'cloudy' => 'cloudy',
            'fog' => 'foggy',
            'drizzle' => 'drizzle',
            'light_rain' => 'light rain',
            'rain' => 'rain',
            'heavy_rain' => 'heavy rain',
            'wintry' => 'wintry',
            'thunder' => 'thundery showers',
            'windy' => 'windy',
            default => 'cloudy',
        };

        if ($windy && $kind !== 'clear' && $kind !== 'windy') {
            return $base . ' and windy';
        }
        if ($windy && $kind === 'clear') {
            return 'bright and windy';
        }

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function httpJson(string $url): ?array
    {
        $raw = $this->httpGet($url);
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Prefer streams over curl — PHP 8.5 + Yii debug treats curl deprecations as failures.
     */
    private function httpGet(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::HTTP_TIMEOUT,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: OwnLane/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw !== false && $raw !== '') {
            return $raw;
        }

        // Fallback curl path without curl_close() (deprecated in PHP 8.5).
        if (!function_exists('curl_init')) {
            Yii::warning('Weather HTTP failed (streams): ' . $url, __METHOD__);
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: OwnLane/1.0',
            ],
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($raw === false || $status >= 400) {
            Yii::warning('Weather HTTP failed (curl ' . $status . '): ' . $url, __METHOD__);
            return null;
        }

        return (string) $raw;
    }
}
