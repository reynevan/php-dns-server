<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class PtrRecord extends Record
{
    protected RecordType $type = RecordType::PTR;

    protected function encodeRdata(): string
    {
        return DomainName::fromString($this->rdata)->encode();
    }
}
