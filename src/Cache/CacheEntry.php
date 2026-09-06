<?php

namespace Reynevan\PhpDnsServer\Cache;

use Reynevan\PhpDnsServer\Resolver\ResolutionResult;

readonly class CacheEntry
{
    public function __construct(private ResolutionResult $result, private int $expiresAt)
    {
    }

    public function getResult(): ResolutionResult
    {
        return $this->result;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }
}
