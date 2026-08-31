<?php

namespace Reynevan\PhpDnsServer\Record;

use InvalidArgumentException;

class ARecord extends Record
{
    protected RecordType $type = RecordType::A;

    protected function encodeRdata(): string
    {
        $binary = @inet_pton($this->rdata);

        if ($binary === false || strlen($binary) !== 4) {
            throw new InvalidArgumentException(sprintf('Invalid IPv4 address: %s', $this->rdata));
        }

        return $binary;
    }
}
