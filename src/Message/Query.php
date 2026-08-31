<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;
use Reynevan\PhpDnsServer\Message\Exception\NotImplementedException;
use Reynevan\PhpDnsServer\Record\RecordType;

class Query
{
    private const int HEADER_LENGTH = 12;

    private string $txId;

    private string $name;

    private RecordType $type;

    private QueryFlags $flags;

    public function getTxId(): string
    {
        return $this->txId;
    }

    public function getType(): RecordType
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __construct(string $buffer)
    {
        $this->txId = substr($buffer, 0, 2);
        $this->flags = new QueryFlags($buffer);
        $name = DomainName::decode(substr($buffer, self::HEADER_LENGTH));
        $this->name = (string) $name;
        $type = unpack('n', substr($buffer, self::HEADER_LENGTH + $name->getEncodedLength(), 2));
        if (!$type || !isset($type[1])) {
            throw new MalformedQueryException();
        }
        $type = RecordType::fromInt($type[1]);
        if (!$type) {
            throw new NotImplementedException();
        }
        $this->type = $type;
        $this->validateFlags();
    }

    public function __toString(): string
    {
        $result = (DomainName::fromString($this->name))->encode();
        $result .= pack('n', $this->type->toInt());
        $result .= pack('n', 1);

        return $result;
    }

    public function getFlags(): QueryFlags
    {
        return $this->flags;
    }

    private function validateFlags(): void
    {
        if ($this->flags->getOpcode() !== Opcode::STANDARD->value) {
            throw new NotImplementedException();
        }
    }
}
