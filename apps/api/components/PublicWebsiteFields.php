<?php

declare(strict_types=1);

namespace app\components;

use app\models\Organisation;

/**
 * Shared encode/decode for public website fields on Organisation.
 */
final class PublicWebsiteFields
{
    /** @var array<string, string> */
    public const TEACHING_STYLE_LABELS = [
        'calm_patient' => 'Calm and patient',
        'structured' => 'Structured lesson plans',
        'nervous_learners' => 'Support for nervous learners',
        'refreshers' => 'Refresher lessons',
        'motorway' => 'Motorway confidence',
    ];

    /**
     * @param mixed $raw
     * @return list<array<string, mixed>>
     */
    public static function encodeServices(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $rows = [];
        foreach ($raw as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $pence = (int) ($row['price_pence'] ?? 0);
            $mins = (int) ($row['duration_minutes'] ?? 0);
            if ($pence <= 0) {
                continue;
            }
            $name = PublicContentSanitizer::singleLine((string) ($row['name'] ?? ''), 120);
            if ($name === null) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'service-' . ($index + 1);
            }
            $type = trim((string) ($row['type'] ?? 'lesson'));
            if (!in_array($type, ['lesson', 'mock_test', 'refresher', 'motorway', 'test_day'], true)) {
                $type = 'lesson';
            }
            $rows[] = [
                'id' => mb_substr($id, 0, 64),
                'name' => $name,
                'description' => PublicContentSanitizer::text($row['description'] ?? null, 500),
                'duration_minutes' => $mins > 0 ? $mins : null,
                'price_pence' => $pence,
                'type' => $type,
                'public' => !isset($row['public']) || (bool) $row['public'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function decodeServices(Organisation $org): array
    {
        $raw = trim((string) ($org->profile_services ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::formatServices($decoded);
            }
        }

        return self::servicesFromLegacyPricing($org);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function servicesFromLegacyPricing(Organisation $org): array
    {
        $raw = trim((string) ($org->profile_public_pricing ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $services = [];
        foreach ($decoded as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $pence = (int) ($row['price_pence'] ?? 0);
            $mins = (int) ($row['duration_minutes'] ?? 0);
            if ($pence <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $services[] = [
                'id' => 'lesson-' . $mins,
                'name' => $label !== '' ? $label : self::durationName($mins),
                'description' => null,
                'duration_minutes' => $mins,
                'price_pence' => $pence,
                'type' => 'lesson',
                'public' => true,
            ];
        }

        return self::formatServices($services);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function formatServices(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['public'])) {
                continue;
            }
            $pence = (int) ($row['price_pence'] ?? 0);
            if ($pence <= 0) {
                continue;
            }
            $mins = isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null;
            $out[] = [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'duration_minutes' => $mins,
                'duration_label' => $mins ? self::durationName($mins) : null,
                'price_pence' => $pence,
                'price_label' => Money::formatPence($pence),
                'type' => (string) ($row['type'] ?? 'lesson'),
            ];
        }

        usort($out, static function ($a, $b) {
            $aMins = (int) ($a['duration_minutes'] ?? 0);
            $bMins = (int) ($b['duration_minutes'] ?? 0);
            if ($aMins === $bMins) {
                return strcmp((string) $a['name'], (string) $b['name']);
            }

            return $aMins <=> $bMins;
        });

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $services
     */
    public static function servicesToLegacyPricing(array $services): ?string
    {
        $rows = [];
        foreach ($services as $row) {
            if (empty($row['public'])) {
                continue;
            }
            $mins = (int) ($row['duration_minutes'] ?? 0);
            $pence = (int) ($row['price_pence'] ?? 0);
            if ($pence <= 0 || $mins < 15) {
                continue;
            }
            $rows[] = [
                'duration_minutes' => $mins,
                'price_pence' => $pence,
                'label' => $row['name'] ?? null,
            ];
        }

        return $rows === [] ? null : json_encode($rows, JSON_THROW_ON_ERROR);
    }

    /**
     * @param mixed $raw
     * @return list<array{question: string, answer: string}>
     */
    public static function encodeFaqs(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $faqs = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = PublicContentSanitizer::singleLine((string) ($row['question'] ?? ''), 200);
            $a = PublicContentSanitizer::text((string) ($row['answer'] ?? ''), 2000);
            if ($q === null || $a === null) {
                continue;
            }
            $faqs[] = ['question' => $q, 'answer' => $a];
        }

        return array_slice($faqs, 0, 12);
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function decodeFaqs(Organisation $org, array $areas, ?string $transmissionLabel): array
    {
        $raw = trim((string) ($org->profile_faqs ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $decoded !== []) {
                return array_values(array_filter(array_map(static function ($row) {
                    if (!is_array($row)) {
                        return null;
                    }
                    $q = PublicContentSanitizer::singleLine((string) ($row['question'] ?? ''), 200);
                    $a = PublicContentSanitizer::text((string) ($row['answer'] ?? ''), 2000);

                    return ($q !== null && $a !== null) ? ['question' => $q, 'answer' => $a] : null;
                }, $decoded)));
            }
        }

        return self::defaultFaqs($org, $areas, $transmissionLabel);
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function defaultFaqs(Organisation $org, array $areas, ?string $transmissionLabel): array
    {
        $areaText = $areas !== [] ? implode(', ', $areas) : 'the areas listed on this page';
        $faqs = [
            [
                'question' => 'What areas do you cover?',
                'answer' => 'I usually teach in ' . $areaText . '.',
            ],
        ];
        if ($transmissionLabel) {
            $faqs[] = [
                'question' => 'Do you teach automatic or manual?',
                'answer' => 'I teach ' . strtolower($transmissionLabel) . ' lessons.',
            ];
        }
        $policy = trim((string) ($org->cancellation_policy ?? ''));
        if ($policy !== '') {
            $faqs[] = [
                'question' => 'What happens if I need to cancel?',
                'answer' => $policy,
            ];
        }
        $faqs[] = [
            'question' => 'How do I pay?',
            'answer' => 'Payment details are arranged directly with your instructor.',
        ];

        return $faqs;
    }

    /**
     * @param mixed $raw
     * @return array<string, string|null>
     */
    public static function encodeSocialLinks(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $map = [
            'instagram' => ['instagram.com'],
            'facebook' => ['facebook.com', 'fb.com'],
            'tiktok' => ['tiktok.com'],
            'youtube' => ['youtube.com', 'youtu.be'],
        ];
        foreach ($map as $key => $hosts) {
            $url = PublicContentSanitizer::socialUrl($raw[$key] ?? null, $hosts);
            if ($url !== null) {
                $out[$key] = $url;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function decodeSocialLinks(Organisation $org): array
    {
        $raw = trim((string) ($org->profile_social_links ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    public static function encodeTeachingStyles(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $styles = [];
        foreach ($raw as $key) {
            $k = (string) $key;
            if (isset(self::TEACHING_STYLE_LABELS[$k])) {
                $styles[] = $k;
            }
        }

        return array_values(array_unique($styles));
    }

    /**
     * @return list<string>
     */
    public static function decodeTeachingStyleLabels(Organisation $org): array
    {
        $raw = trim((string) ($org->profile_teaching_styles ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $labels = [];
        foreach ($decoded as $key) {
            if (isset(self::TEACHING_STYLE_LABELS[$key])) {
                $labels[] = self::TEACHING_STYLE_LABELS[$key];
            }
        }

        return $labels;
    }

    public static function durationName(int $mins): string
    {
        if ($mins % 60 === 0) {
            $h = (int) ($mins / 60);

            return $h === 1 ? '1 hour' : $h . ' hours';
        }

        return $mins . ' minutes';
    }

    /**
     * @param array<string, mixed> $mode
     */
    public static function ctaLabel(array $mode): ?string
    {
        return match ($mode['code'] ?? '') {
            'open' => 'Ask about lessons',
            'limited' => 'Ask about availability',
            'waiting_list' => 'Join waiting list',
            default => ($mode['allows_waiting_list'] ?? false) ? 'Join waiting list' : null,
        };
    }

    public static function seoTitle(string $displayName, ?string $transmissionLabel, array $areas): string
    {
        $place = $areas[0] ?? 'your area';
        $tx = $transmissionLabel ? $transmissionLabel . ' driving lessons' : 'Driving lessons';

        return $displayName . ' | ' . $tx . ' in ' . $place;
    }

    public static function seoDescription(
        string $displayName,
        ?string $intro,
        array $areas,
        ?string $pricingFrom,
    ): string {
        $bits = [];
        if ($intro) {
            $bits[] = mb_substr($intro, 0, 120);
        }
        if ($areas !== []) {
            $bits[] = 'Teaching in ' . implode(', ', array_slice($areas, 0, 4));
        }
        if ($pricingFrom) {
            $bits[] = $pricingFrom;
        }
        if ($bits === []) {
            $bits[] = $displayName . ' — driving instructor';
        }

        return implode('. ', $bits);
    }
}
