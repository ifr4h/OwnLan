<?php

declare(strict_types=1);

namespace app\components;

/**
 * Rough UK teaching-area fit from postcode vs instructor-stated areas.
 */
final class TeachingAreaMatcher
{
    /**
     * @param list<string> $teachingAreas
     * @return array{status: string, label: string}
     */
    public function check(string $postcode, array $teachingAreas, ?string $serviceArea = null): array
    {
        $postcode = $this->normalizePostcode($postcode);
        if ($postcode === '') {
            return [
                'status' => 'unknown',
                'label' => 'Postcode not recognised — instructor will check manually.',
            ];
        }

        $outward = $this->outwardCode($postcode);
        $haystack = [];
        foreach ($teachingAreas as $area) {
            $haystack[] = strtolower(trim($area));
        }
        if ($serviceArea !== null && trim($serviceArea) !== '') {
            $haystack[] = strtolower(trim($serviceArea));
        }
        if ($haystack === []) {
            return [
                'status' => 'unknown',
                'label' => 'Teaching area not set on profile.',
            ];
        }

        foreach ($haystack as $area) {
            if ($area === '') {
                continue;
            }
            $areaPc = $this->normalizePostcode($area);
            if ($areaPc !== '') {
                if ($areaPc === $postcode) {
                    return ['status' => 'within', 'label' => 'Inside usual teaching area'];
                }
                if ($this->outwardCode($areaPc) === $outward) {
                    return ['status' => 'within', 'label' => 'Inside usual teaching area'];
                }
            }
            if (str_contains($area, strtolower($outward)) || str_contains($area, strtolower($postcode))) {
                return ['status' => 'within', 'label' => 'Inside usual teaching area'];
            }
        }

        return [
            'status' => 'outside',
            'label' => 'May be outside usual teaching area',
        ];
    }

    private function normalizePostcode(string $input): string
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $input) ?? ''));
        if (preg_match('/\b([a-z]{1,2}\d[a-z\d]?\s*\d[a-z]{2})\b/', $text, $m) !== 1) {
            return '';
        }

        return strtoupper(preg_replace('/\s+/', '', $m[1]) ?? $m[1]);
    }

    private function outwardCode(string $compactPostcode): string
    {
        return substr($compactPostcode, 0, -3);
    }
}
