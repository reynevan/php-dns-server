<?php

namespace Reynevan\PhpDnsServer\Cache;

use Reynevan\PhpDnsServer\Resolver\ResolutionResult;

interface Cache
{
    public function get(CacheKey $key): ?ResolutionResult;
    public function set(CacheKey $key, ResolutionResult $result): void;
    public function delete(CacheKey $key): void;
}
