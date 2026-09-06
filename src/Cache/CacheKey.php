<?php

namespace Reynevan\PhpDnsServer\Cache;

use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Question;
use Reynevan\PhpDnsServer\Record\RecordClass;
use Reynevan\PhpDnsServer\Record\RecordType;

readonly class CacheKey
{
    public function __construct(
        private DomainName $name,
        private RecordType $recordType,
        private RecordClass $recordClass = RecordClass::IN
    ) {
    }

    public static function forQuestion(Question $question): self
    {
        return new self($question->getName(), $question->getType(), $question->getClass());
    }

    public function __toString(): string
    {
        return strtolower($this->name . '|' . $this->recordType->toInt() . '|' . $this->recordClass->toInt());
    }

    public function getName(): string
    {
        return $this->name;
    }
}
