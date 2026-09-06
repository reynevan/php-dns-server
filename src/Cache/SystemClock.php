<?php

namespace Reynevan\PhpDnsServer\Cache;

class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
