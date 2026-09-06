<?php

namespace Reynevan\PhpDnsServer\Record;

use InvalidArgumentException;

class ARecord extends Record
{
    protected RecordType $type = RecordType::A;

    protected function encodeRdata(): string
    {
        $binary = @inet_pton($this->rData);

        if ($binary === false || strlen($binary) !== 4) {
            throw new InvalidArgumentException(sprintf('Invalid IPv4 address: %s', $this->rData));
        }

        return $binary;
    }

    public function setEncodedRdata(string $buffer, int $offset): static
    {
        $this->rData = long2ip(unpack('N', substr($buffer, $offset, 4))[1]);
        return $this;
    }
}
