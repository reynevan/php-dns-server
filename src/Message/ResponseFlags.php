<?php

namespace Reynevan\PhpDnsServer\Message;

readonly class ResponseFlags
{
    public function __construct(
        private bool $authoritative = false,
        private bool $truncated = false,
        private bool $recursionDesired = false,
        private bool $recursionAvailable = false,
        private bool $answerAuthenticated = false,
        private bool $nonAuthDataAcceptable = false,
        private ?Opcode $opcode = Opcode::STANDARD,
        private ?ResponseCode $rCode = ResponseCode::NOERROR
    ) {
    }

    public static function fromBuffer(string $buffer): self
    {
        $buffer = unpack('n', $buffer)[1];
        $opcode = Opcode::tryFrom(($buffer >> 11) & 0xF);
        $authoritative = (($buffer >> 10) & 0x1) === 1;
        $truncated = (($buffer >> 9) & 0x1) === 1;
        $recursionDesired = (($buffer >> 8) & 0x1) === 1;
        $recursionAvailable = (($buffer >> 7) & 0x1) === 1;
        $answerAuthenticated = (($buffer >> 5) & 0x1) === 1;
        $nonAuthDataAcceptable = (($buffer >> 4) & 0x1) === 1;
        $rcode = ResponseCode::tryFrom(($buffer) & 0x0F);
        return new self(
            $authoritative,
            $truncated,
            $recursionDesired,
            $recursionAvailable,
            $answerAuthenticated,
            $nonAuthDataAcceptable,
            $opcode,
            $rcode
        );
    }

    public function withTruncated(): self
    {
        return new self(
            $this->authoritative,
            true,
            $this->recursionDesired,
            $this->recursionAvailable,
            $this->answerAuthenticated,
            $this->nonAuthDataAcceptable,
            $this->opcode,
            $this->rCode
        );
    }

    public function encode(): string
    {
        $flags =
            1 << 15
            | ($this->opcode->value << 11)
            | (($this->authoritative ? 1 : 0) << 10)
            | (($this->truncated ? 1 : 0) << 9)
            | (($this->recursionDesired ? 1 : 0) << 8)
            | (($this->recursionAvailable ? 1 : 0) << 7)
            | (($this->answerAuthenticated ? 1 : 0) << 5)
            | (($this->nonAuthDataAcceptable ? 1 : 0) << 4)
            | $this->rCode->value
            ;

        return pack('n', $flags);
    }

    public function getOpcode(): ?Opcode
    {
        return $this->opcode;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function isRecursionDesired(): bool
    {
        return $this->recursionDesired;
    }

    public function isRecursionAvailable(): bool
    {
        return $this->recursionAvailable;
    }

    public function isAnswerAuthenticated(): bool
    {
        return $this->answerAuthenticated;
    }

    public function isNonAuthDataAcceptable(): bool
    {
        return $this->nonAuthDataAcceptable;
    }

    public function getRCode(): ?ResponseCode
    {
        return $this->rCode;
    }

    public function isAuthoritative(): bool
    {
        return $this->authoritative;
    }
}
