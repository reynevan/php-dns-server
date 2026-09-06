<?php

namespace Reynevan\PhpDnsServer\Cache;

use Reynevan\PhpDnsServer\Resolver\ResolutionResult;

final class InMemoryCache implements Cache
{
    /** @var array<string, CacheEntry> */
    private array $entries = [];

    public function __construct(
        private readonly TtlPolicy $policy = new TtlPolicy(),
        private readonly Clock $clock = new SystemClock(),
        private readonly int $maxEntries = 10_000,
    ) {
    }

    public function get(CacheKey $key): ?ResolutionResult
    {
        $entry = $this->entries[(string) $key] ?? null;
        if ($entry === null) {
            return null;
        }

        $remaining = $entry->getExpiresAt() - $this->clock->now();
        if ($remaining <= 0) {
            unset($this->entries[(string) $key]);

            return null;
        }
        return $entry->getResult()->withTtl($remaining);
    }

    public function set(CacheKey $key, ResolutionResult $result): void
    {
        $ttl = $this->policy->ttlFor($result);
        if ($ttl === null) {
            return;
        }

        unset($this->entries[(string) $key]);
        $this->entries[(string) $key] = new CacheEntry($result, $this->clock->now() + $ttl);

        if (count($this->entries) > $this->maxEntries) {
            array_shift($this->entries);
        }
    }

    public function delete(CacheKey $key): void
    {
        unset($this->entries[(string) $key]);
    }
}
