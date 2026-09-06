<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Record\RecordClass;
use Reynevan\PhpDnsServer\Record\RecordType;

readonly class Question
{
    public function __construct(
        private DomainName $name,
        private RecordType $type,
        private RecordClass $class = RecordClass::IN
    ) {
    }

    public function getType(): RecordType
    {
        return $this->type;
    }

    public function getName(): DomainName
    {
        return $this->name;
    }

    public function getClass(): RecordClass
    {
        return $this->class;
    }

    public static function fromBuffer(string $buffer): self
    {
        $name = DomainName::decode($buffer);
        $type = RecordType::fromInt(unpack('n', substr($buffer, strlen($name) + 1, 2))[1]);
        $class = RecordClass::fromInt(unpack('n', substr($buffer, strlen($name) + 3, 2))[1]);
        return new self($name, $type, $class);
    }

    public function encode(): string
    {
        $result = $this->name->encode();
        $result .= pack('n', $this->type->toInt());
        $result .= pack('n', $this->class->toInt());

        return $result;
    }
}
