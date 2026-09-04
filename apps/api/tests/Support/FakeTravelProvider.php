<?php

declare(strict_types=1);

namespace app\tests\Support;

use app\travel\TravelTimeProvider;

/**
 * Deterministic travel provider for tests — map "from|to" → minutes.
 */
final class FakeTravelProvider implements TravelTimeProvider
{
    /** @param array<string, int|null> $matrix */
    public function __construct(private array $matrix = [])
    {
    }

    public function estimateDriveMinutes(?string $fromAddress, ?string $toAddress): ?int
    {
        if ($fromAddress === null || $toAddress === null
            || trim($fromAddress) === '' || trim($toAddress) === '') {
            return null;
        }
        $key = $this->key($fromAddress, $toAddress);
        if (array_key_exists($key, $this->matrix)) {
            return $this->matrix[$key];
        }
        $reverse = $this->key($toAddress, $fromAddress);
        if (array_key_exists($reverse, $this->matrix)) {
            return $this->matrix[$reverse];
        }

        return null;
    }

    public function set(string $from, string $to, ?int $minutes): void
    {
        $this->matrix[$this->key($from, $to)] = $minutes;
    }

    private function key(string $from, string $to): string
    {
        return strtolower(trim($from)) . '|' . strtolower(trim($to));
    }
}
