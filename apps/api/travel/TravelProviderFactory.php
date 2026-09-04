<?php

declare(strict_types=1);

namespace app\travel;

/**
 * Factory — swap providers later without touching feasibility logic.
 */
final class TravelProviderFactory
{
    public static function make(): TravelTimeProvider
    {
        $name = strtolower(trim((string) (getenv('TRAVEL_PROVIDER') ?: 'heuristic')));

        return match ($name) {
            // Future: 'google', 'mapbox', …
            default => new HeuristicTravelProvider(),
        };
    }
}
