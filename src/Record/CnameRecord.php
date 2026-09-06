<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class CnameRecord extends Record
{
    protected RecordType $type = RecordType::CNAME;

    protected function encodeRdata(): string
    {
        return DomainName::fromString($this->rData)->encode();
    }

    public function setEncodedRdata(string $buffer, int $offset): static
    {
        $this->rData = (string) DomainName::decode($buffer, $offset);
        return $this;
    }
}
