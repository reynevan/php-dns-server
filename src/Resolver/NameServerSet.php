<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;

class NameServerSet
{
    private int $index = 0;

    /** @var array<string, true> */
    private array $dead = [];

    /**
     * @param NameServer[] $nameServers
     */
    private function __construct(private readonly array $nameServers)
    {
    }


    public static function fromRecords(RecordSet $nsRecords, RecordSet $glue): self
    {
        $addresses = [];
        foreach ($glue->toArray() as $record) {
            if (!in_array($record->getType(), [RecordType::A, RecordType::AAAA], true)) {
                continue;
            }
            $key = strtolower((string) $record->getName());
            $addresses[$key][$record->getType()->value][] = $record->getRData();
        }

        $servers = [];
        foreach ($nsRecords->ofType(RecordType::NS)->toArray() as $record) {
            $name = new DomainName($record->getRData());
            $own = $addresses[strtolower((string) $name)] ?? [];
            $servers[] = new NameServer(
                $name,
                $own[RecordType::A->value] ?? [],
                $own[RecordType::AAAA->value] ?? [],
            );
        }

        return new self($servers);
    }

    public function next(): ?NameServer
    {
        $count = count($this->nameServers);
        for ($i = 0; $i < $count; $i++) {
            if (!isset($this->nameServers[$this->index])) {
                $this->index = 0;
            }
            $ns = $this->nameServers[$this->index];
            $this->index++;
            if (!isset($this->dead[self::key($ns)])) {
                return $ns;
            }
        }

        return null;
    }

    public function markDead(NameServer $nameServer): void
    {
        $this->dead[self::key($nameServer)] = true;
    }

    private static function key(NameServer $nameServer): string
    {
        return strtolower((string) $nameServer->getName());
    }
}
