<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class MxRecord extends Record
{
    protected RecordType $type = RecordType::MX;

    protected function encodeRdata(): string
    {
        $dataParts = explode(' ', $this->rData);
        $preference = $dataParts[0];
        $value = $dataParts[1];
        $result = pack('n', (int) $preference);
        $result .= (DomainName::fromString($value))->encode();

        return $result;
    }

    public function setEncodedRdata(string $buffer, int $offset): static
    {
        $preference = unpack('n', substr($buffer, $offset, 2))[1];
        $offset += 2;
        $mx = DomainName::decode($buffer, $offset);
        $this->rData = $preference . ' ' . $mx;
        return $this;
    }
}
