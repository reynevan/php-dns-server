<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class CnameRecord extends Record
{
    protected RecordType $type = RecordType::CNAME;

    protected function encodeRdata(): string
    {
        return DomainName::fromString($this->rdata)->encode();
    }
}
