<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;

class NameServer
{
    /**
     * @param DomainName $name
     * @param string[] $addressA
     * @param string[] $addressAaaa
     */
    public function __construct(
        readonly private DomainName $name,
        private array $addressA = [],
        private array $addressAaaa = []
    ) {
    }

    public function getName(): DomainName
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getAddressA(): array
    {
        return $this->addressA;
    }

    /**
     * @return string[]
     */
    public function getAddressAaaa(): array
    {
        return $this->addressAaaa;
    }

    public function withAddresses(RecordSet $addresses): self
    {
        $addressesA = array_map(fn(Record $r) => $r->getRData(), $addresses->ofType(RecordType::A)->toArray());
        $addressesAaaa = array_map(fn(Record $r) => $r->getRData(), $addresses->ofType(RecordType::AAAA)->toArray());
        $addressesA = array_merge($this->addressA, $addressesA);
        $addressesAaaa = array_merge($this->addressAaaa, $addressesAaaa);
        return new self($this->name, $addressesA, $addressesAaaa);
    }
}
