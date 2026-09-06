<?php

namespace Reynevan\PhpDnsServer\Zone;

use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;

class Zone
{
    public function __construct(private readonly DomainName $origin, private RecordSet $records)
    {
    }

    public function getOrigin(): DomainName
    {
        return $this->origin;
    }

    public function getRecords(): RecordSet
    {
        return $this->records;
    }

    public function findByName(DomainName $name): RecordSet
    {
        return $this->records->ofName($name);
    }

    public function findByNameAndType(DomainName $name, RecordType $type): RecordSet
    {
        return $this->records->ofName($name)->ofType($type);
    }

    public function hasName(DomainName $name): bool
    {
        return !$this->findByName($name)->isEmpty();
    }

    public function isAuthoritativeFor(DomainName $name): bool
    {
        return $name->isWithin($this->origin);
    }
}
