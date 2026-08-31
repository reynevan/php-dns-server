<?php

namespace Reynevan\PhpDnsServer\Zone;

use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Record\Record;

class Zone
{
    /**
     * @var Record[]
     */
    private array $records = [];

    public function addRecord(Record $record): void
    {
        $this->records[] = $record;
    }

    /**
     * @return Record[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function lookup(Query $query): LookupResult
    {
        $queriedName = DomainName::fromString($query->getName());
        $byName = array_filter(
            $this->records,
            fn($r) => DomainName::fromString($r->getName())->equals($queriedName)
        );

        if (!count($byName)) {
            return new LookupResult([], ResponseCode::NXDOMAIN);
        }

        $byType = array_values(array_filter(
            $byName,
            fn($r) => $r->getType() === $query->getType()
        ));

        return new LookupResult($byType, ResponseCode::NOERROR);
    }
}
