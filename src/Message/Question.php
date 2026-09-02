<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Record\RecordClass;
use Reynevan\PhpDnsServer\Record\RecordType;

class Question
{
    public function __construct(private DomainName $name, private RecordType $type, private RecordClass $class = RecordClass::IN)
    {

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

    public static function decode(string $buffer): self
    {

        return new self();
    }

    public function encode(): string
    {
        $result = $this->name->encode();
        $result .= pack('n', $this->type->toInt());
        $result .= pack('n', $this->class->toInt());

        return $result;
    }
}