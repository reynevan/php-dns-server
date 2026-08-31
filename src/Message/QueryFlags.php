<?php

namespace Reynevan\PhpDnsServer\Message;

class QueryFlags
{
    private int $flags;

    public function __construct(string $buffer)
    {
        $this->flags = unpack('n', substr($buffer, 2, 2))[1] >> 4;
    }

    public function getOpcode(): int
    {
        return ($this->flags >> 7) & 0xF;
    }

    public function getResponse(): int
    {
        return ($this->flags >> 11) & 0x1;
    }

    public function getTruncated(): int
    {
        return ($this->flags >> 5) & 0x1;
    }

    public function getRecursion(): int
    {
        return ($this->flags >> 4) & 0x1;
    }
}
