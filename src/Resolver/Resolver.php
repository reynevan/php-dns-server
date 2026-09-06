<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Message\Query;

interface Resolver
{
    public function supports(Query $query): bool;

    public function resolve(Query $query): ResolutionResult;
}
