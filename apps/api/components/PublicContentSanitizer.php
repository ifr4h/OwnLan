<?php

declare(strict_types=1);

namespace app\components;

/**
 * Plain-text sanitisation for instructor-authored public content.
 */
final class PublicContentSanitizer
{
    public static function text(?string $value, int $maxLength = 4000): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim($value);
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $text) ?? '';
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $text) ?? '';
        $text = trim(strip_tags($text));
        $text = preg_replace('/\r\n?/', "\n", $text) ?? '';
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }

    public static function singleLine(?string $value, int $maxLength = 255): ?string
    {
        $text = self::text($value, $maxLength);
        if ($text === null) {
            return null;
        }

        return str_replace("\n", ' ', $text);
    }

    /**
     * @return string|null Normalised #RRGGBB or null if invalid
     */
    public static function accentColour(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $hex = strtoupper(trim($value));
        if (preg_match('/^#([0-9A-F]{6})$/', $hex) !== 1) {
            return null;
        }

        return $hex;
    }

    /**
     * Pick a readable on-accent text colour.
     */
    public static function accentForeground(string $accentHex): string
    {
        $hex = ltrim($accentHex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.55 ? '#1A1A1A' : '#FFFFFF';
    }

    /**
     * @return string|null Validated http(s) URL
     */
    public static function socialUrl(?string $url, array $allowedHosts = []): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if ($allowedHosts !== []) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $ok = false;
            foreach ($allowedHosts as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return null;
            }
        }

        return $url;
    }

    public static function whatsappLink(?string $number): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $number) ?? '';
        if (strlen($digits) < 10) {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '44' . substr($digits, 1);
        }

        return 'https://wa.me/' . $digits;
    }
}
