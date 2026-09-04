<?php

declare(strict_types=1);

namespace app\travel;

/**
 * Swappable travel-time source for Smart Day.
 *
 * Keep this surface tiny — minutes between two pickup addresses, or null if unknown.
 */
interface TravelTimeProvider
{
    /**
     * Estimated driving time in whole minutes.
     * Return null when an estimate cannot be made (missing addresses, provider failure).
     */
    public function estimateDriveMinutes(?string $fromAddress, ?string $toAddress): ?int;
}
