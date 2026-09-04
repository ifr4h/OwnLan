<?php

declare(strict_types=1);

namespace app\components;

/**
 * UTF-8 CSV with formula-injection protection.
 */
final class CsvExporter
{
    /**
     * @param list<string> $headers
     * @param list<list<string|int|null>> $rows
     */
    public static function build(array $headers, array $rows): string
    {
        $lines = [self::row($headers)];
        foreach ($rows as $row) {
            $lines[] = self::row($row);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string|int|null> $cells
     */
    public static function row(array $cells): string
    {
        $out = [];
        foreach ($cells as $cell) {
            $value = self::escapeCell((string) ($cell ?? ''));
            $out[] = $value;
        }

        return implode(',', $out);
    }

    /**
     * Prevent spreadsheet formula injection on user-entered values.
     */
    public static function escapeCell(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $value = "'" . $value;
        }
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    public static function poundsFromPence(int $pence): string
    {
        $sign = $pence < 0 ? '-' : '';
        $abs = abs($pence);

        return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
