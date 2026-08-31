<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class MxRecord extends Record
{
    protected RecordType $type = RecordType::MX;

    protected function encodeRdata(): string
    {
        $dataParts = explode(' ', $this->rdata);
        $preference = $dataParts[0];
        $value = $dataParts[1];
        $result = pack('n', (int) $preference);
        $result .= (DomainName::fromString($value))->encode();

        return $result;
    }
}
