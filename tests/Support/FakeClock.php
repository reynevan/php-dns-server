<?php

namespace Reynevan\PhpDnsServer\Tests\Support;

use Reynevan\PhpDnsServer\Cache\Clock;

class FakeClock implements Clock
{
    public function __construct(private int $now = 1_000_000)
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
