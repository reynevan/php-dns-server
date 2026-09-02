<?php

namespace Reynevan\PhpDnsServer\Transport;

use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\Response;
use Reynevan\PhpDnsServer\Resolver\NameServer;

interface Transport
{
    public function ask(NameServer $ns, Query $query): Response;
}