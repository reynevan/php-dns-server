<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class NsRecord extends Record
{
    protected RecordType $type = RecordType::NS;

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
