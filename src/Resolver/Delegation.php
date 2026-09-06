<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Message\DomainName;

final readonly class Delegation
{
    public function __construct(
        public DomainName $zone,
        public NameServerSet $nameServers,
    ) {
    }
}
