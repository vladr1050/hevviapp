<?php

declare(strict_types=1);

namespace App\Service\WaitingList;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Simple IP-based rate limit for the public waiting-list endpoint.
 */
final class WaitingListRateLimiter
{
    private const MAX_ATTEMPTS = 10;

    private const WINDOW_SECONDS = 3600;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function isLimited(string $clientIp): bool
    {
        return $this->getCount($clientIp) >= self::MAX_ATTEMPTS;
    }

    public function recordAttempt(string $clientIp): void
    {
        $item = $this->cache->getItem($this->cacheKey($clientIp));
        $count = $item->isHit() ? (int) $item->get() : 0;
        $item->set($count + 1);
        $item->expiresAfter(self::WINDOW_SECONDS);
        $this->cache->save($item);
    }

    private function getCount(string $clientIp): int
    {
        $item = $this->cache->getItem($this->cacheKey($clientIp));
        if (!$item->isHit()) {
            return 0;
        }

        return (int) $item->get();
    }

    private function cacheKey(string $clientIp): string
    {
        return 'waiting_list_rate_'.hash('sha256', $clientIp);
    }
}
