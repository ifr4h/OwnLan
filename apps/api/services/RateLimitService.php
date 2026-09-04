<?php

declare(strict_types=1);

namespace app\services;

use Yii;
use yii\web\TooManyRequestsHttpException;

/**
 * Lightweight cache-backed rate limiting for auth surfaces.
 */
class RateLimitService
{
    /**
     * @throws TooManyRequestsHttpException
     */
    public function hit(string $key, int $maxAttempts, int $windowSeconds, string $userMessage): void
    {
        $cache = Yii::$app->cache;
        $now = time();
        /** @var array{count: int, reset_at: int}|false $bucket */
        $bucket = $cache->get($key);
        if ($bucket === false) {
            $cache->set($key, ['count' => 1, 'reset_at' => $now + $windowSeconds], $windowSeconds);

            return;
        }

        if ($bucket['reset_at'] <= $now) {
            $cache->set($key, ['count' => 1, 'reset_at' => $now + $windowSeconds], $windowSeconds);

            return;
        }

        if ($bucket['count'] >= $maxAttempts) {
            throw new TooManyRequestsHttpException($userMessage);
        }

        $bucket['count']++;
        $cache->set($key, $bucket, max(1, $bucket['reset_at'] - $now));
    }

    public function secondsUntilReset(string $key): int
    {
        /** @var array{count: int, reset_at: int}|false $bucket */
        $bucket = Yii::$app->cache->get($key);
        if ($bucket === false) {
            return 0;
        }
        $remaining = (int) $bucket['reset_at'] - time();

        return max(0, $remaining);
    }
}
