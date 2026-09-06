<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Zone\ZoneFileParser;

readonly class RootHints
{
    private function __construct(private NameServerSet $nameServers)
    {
    }

    public static function fromFile(string $path): self
    {
        $zone = new ZoneFileParser()->parse(file_get_contents($path));
        return new self(NameServerSet::fromRecords(
            $zone->findByNameAndType($zone->getOrigin(), RecordType::NS),
            $zone->getRecords(),
        ));
    }

    public function getNameServers(): NameServerSet
    {
        return $this->nameServers;
    }
}
