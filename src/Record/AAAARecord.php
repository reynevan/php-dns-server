<?php

namespace Reynevan\PhpDnsServer\Record;

use InvalidArgumentException;

class AAAARecord extends Record
{
    protected RecordType $type = RecordType::AAAA;

    protected function encodeRdata(): string
    {
        $binary = @inet_pton($this->rData);

        if ($binary === false || strlen($binary) !== 16) {
            throw new InvalidArgumentException(sprintf('Invalid IPv6 address: %s', $this->rData));
        }

        return $binary;
    }

    public function setEncodedRdata(string $buffer, int $offset): static
    {
        $this->rData = inet_ntop(substr($buffer, $offset, 16));
        return $this;
    }
}
