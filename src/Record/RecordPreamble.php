<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;
use Reynevan\PhpDnsServer\Message\Opt;

readonly class RecordPreamble
{
    private const int LENGTH = 10;

    private function __construct(
        private DomainName $name,
        private int $type,
        private int $class,
        private int $ttl,
        private int $rdLength
    ) {
    }

    /**
     * @throws MalformedQueryException
     */
    public static function decode(string $buffer, int &$offset): self
    {
        $name = DomainName::decode($buffer, $offset);

        $fields = substr($buffer, $offset, self::LENGTH);
        if (strlen($fields) < self::LENGTH) {
            throw new MalformedQueryException();
        }
        $offset += self::LENGTH;

        $fields = unpack('ntype/nclass/Nttl/nrdLength', $fields);

        return new self($name, $fields['type'], $fields['class'], $fields['ttl'], $fields['rdLength']);
    }

    public function isOpt(): bool
    {
        return $this->type === Opt::TYPE;
    }

    public function getName(): DomainName
    {
        return $this->name;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getRecordType(): ?RecordType
    {
        return RecordType::fromInt($this->type);
    }

    public function getClass(): int
    {
        return $this->class;
    }

    public function getRecordClass(): ?RecordClass
    {
        return RecordClass::fromInt($this->class);
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function getRdLength(): int
    {
        return $this->rdLength;
    }
}
