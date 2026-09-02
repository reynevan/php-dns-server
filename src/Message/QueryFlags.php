<?php

namespace Reynevan\PhpDnsServer\Message;

class QueryFlags
{

    public function __construct(
        private bool $isTruncated = false,
        private bool $recursionDesired = false,
        private ?Opcode $opcode = Opcode::STANDARD
    )
    {
    }

    public static function fromBuffer(string $buffer): self
    {
        $buffer = unpack('n', substr($buffer, 2, 2))[1] >> 4;
        $opcode = ($buffer >> 7) & 0xF;
        $isTruncated = (($buffer >> 5) & 0x1) === 1;
        $recursionDesired = (($buffer >> 4) & 0x1) === 1;
        return new self($isTruncated, $recursionDesired, Opcode::tryFrom($opcode));
    }

    public function encode(): string
    {
        $flags =
            ($this->opcode->value << 11)
            | (($this->isTruncated ? 1 : 0) << 9)
            | (($this->recursionDesired ? 1 : 0) << 8);

        return pack('n', $flags);
    }

    public function getOpcode(): ?Opcode
    {
        return $this->opcode;
    }

    public function isTruncated(): bool
    {
        return $this->isTruncated;
    }

    public function isRecursionDesired(): bool
    {
        return $this->recursionDesired;
    }
}
