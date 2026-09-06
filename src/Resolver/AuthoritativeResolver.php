<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Zone\Zone;

readonly class AuthoritativeResolver implements Resolver
{
    public function __construct(private Zone $zone)
    {
    }

    public function supports(Query $query): bool
    {
        return $this->zone->isAuthoritativeFor($query->getQuestion()->getName());
    }

    public function resolve(Query $query): ResolutionResult
    {
        if (!$this->zone->hasName($query->getQuestion()->getName())) {
            $soa = $this->zone->getRecords()->firstOfType(RecordType::SOA);
            return ResolutionResult::noData(authoritative: true, soa: $soa);
        }
        $results = $this->zone->findByNameAndType($query->getQuestion()->getName(), $query->getQuestion()->getType());
        if (!$results->isEmpty()) {
            return ResolutionResult::answer($results, authoritative: true);
        }
        $soa = $this->zone->getRecords()->firstOfType(RecordType::SOA);
        return ResolutionResult::noData(authoritative: true, soa: $soa);
    }
}
