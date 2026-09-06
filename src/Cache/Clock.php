<?php

namespace Reynevan\PhpDnsServer\Cache;

interface Clock
{
    public function now(): int;
}
