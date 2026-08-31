<?php

namespace Reynevan\PhpDnsServer\Record;

use InvalidArgumentException;

class AAAARecord extends Record
{
    protected RecordType $type = RecordType::AAAA;

    protected function encodeRdata(): string
    {
        $binary = @inet_pton($this->rdata);

        if ($binary === false || strlen($binary) !== 16) {
            throw new InvalidArgumentException(sprintf('Invalid IPv6 address: %s', $this->rdata));
        }

        return $binary;
    }
}
