<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Cache\Cache;
use Reynevan\PhpDnsServer\Cache\CacheKey;
use Reynevan\PhpDnsServer\Message\Query;

readonly class CachedResolver implements Resolver
{
    public function __construct(private Resolver $inner, private Cache $cache)
    {
    }

    public function supports(Query $query): bool
    {
        return $this->inner->supports($query);
    }

    public function resolve(Query $query): ResolutionResult
    {
        $key = CacheKey::forQuestion($query->getQuestion());

        if ($hit = $this->cache->get($key)) {
            return $hit;
        }

        $result = $this->inner->resolve($query);
        $this->cache->set($key, $result);

        return $result;
    }
}
