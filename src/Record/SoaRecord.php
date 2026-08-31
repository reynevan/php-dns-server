<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class SoaRecord extends Record
{
    protected RecordType $type = RecordType::SOA;

    protected function encodeRdata(): string
    {
        $data = str_replace(['(', ')'], '', $this->rdata);
        $data = preg_replace('/\s+/', ' ', $data);
        $data = trim($data);
        $parts = explode(' ', $data);
        $min = array_pop($parts);
        $expire = array_pop($parts);
        $retry = array_pop($parts);
        $refresh = array_pop($parts);
        $serial = array_pop($parts);
        $rName = array_pop($parts);
        $mName = array_pop($parts);
        return implode('', [
            (DomainName::fromString($mName))->encode(),
            (DomainName::fromString($rName))->encode(),
            pack('N', (int)$serial),
            pack('N', (int)$refresh),
            pack('N', (int)$retry),
            pack('N', (int)$expire),
            pack('N', (int)$min)
        ]);
    }
}
