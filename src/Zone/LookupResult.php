<?php

namespace Reynevan\PhpDnsServer\Zone;

use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Record\Record;

class LookupResult
{
    /**
     * @param Record[] $records
     * @param ResponseCode $rCode
     */
    public function __construct(private array $records, private ResponseCode $rCode)
    {
    }

    /**
     * @return Record[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function getRCode(): ResponseCode
    {
        return $this->rCode;
    }
}
