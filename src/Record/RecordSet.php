<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

readonly class RecordSet
{
    /**
     * @param Record[] $records
     */
    public function __construct(private array $records = [])
    {
    }

    public function hasType(RecordType $type): bool
    {
        return !$this->ofType($type)->isEmpty();
    }

    public function ofType(RecordType $type): self
    {
        $filterFn = fn($r) => $r->getType() === $type;

        return new self(array_values(array_filter($this->records, $filterFn)));
    }

    public function ofName(DomainName $name): self
    {
        $filterFn = fn($r) => $r->getName()->equals($name);

        return new self(array_values(array_filter($this->records, $filterFn)));
    }

    public function firstOfType(RecordType $type): ?Record
    {
        return $this->ofType($type)->toArray()[0] ?? null;
    }

    public function lastOfType(RecordType $type): ?Record
    {
        $records = $this->ofType($type)->toArray();
        return array_pop($records) ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->records === [];
    }

    public function merge(self $other): self
    {
        return new self(array_merge($other->toArray(), $this->records));
    }

    /**
     * @return Record[]
     */
    public function toArray(): array
    {
        return $this->records;
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function encode(): string
    {
        return implode('', array_map(strval(...), $this->records));
    }

    public function withTtl(int $ttl): self
    {
        return new self(array_map(fn(Record $record) => $record->withTtl($ttl), $this->records));
    }
}
