<?php

namespace Reynevan\PhpDnsServer\Transport;

use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\Response;

interface Transport
{
    public function ask(string $address, Query $query): ?Response;
}
