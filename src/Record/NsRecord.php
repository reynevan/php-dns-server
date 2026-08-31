<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class NsRecord extends Record
{
    protected RecordType $type = RecordType::NS;

    protected function encodeRdata(): string
    {
        return DomainName::fromString($this->rdata)->encode();
    }
}
