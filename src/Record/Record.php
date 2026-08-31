<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class Record
{
    protected string $name;
    protected string $rdata;
    protected int $ttl;
    protected RecordClass $class;
    protected RecordType $type;

    public function __construct(?RecordType $type = null, ?RecordClass $class = null)
    {
        if ($type !== null) {
            $this->type = $type;
        }
        if ($class !== null) {
            $this->class = $class;
        }
    }

    public static function create(RecordType $type, RecordClass $class): ?Record
    {
        $record = match ($type) {
            RecordType::A => new ARecord(),
            RecordType::AAAA => new AAAARecord(),
            RecordType::MX => new MxRecord(),
            RecordType::SOA => new SoaRecord(),
            RecordType::CNAME => new CnameRecord(),
            RecordType::CAA => new CaaRecord(),
            RecordType::NS => new NsRecord(),
            RecordType::PTR => new PtrRecord(),
            default => null
        };
        if ($record) {
            return $record;
        }
        return new self($type, $class);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRdata(): string
    {
        return $this->rdata;
    }

    public function setRdata(string $rdata): static
    {
        $this->rdata = $rdata;

        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    public function getType(): RecordType
    {
        return $this->type;
    }

    protected function encodeName(): string
    {
        return DomainName::fromString($this->name)->encode();
    }

    protected function encodeType(): string
    {
        return pack('n', $this->getType()->toInt());
    }

    protected function encodeTtl(): string
    {
        return pack('N', $this->getTtl());
    }

    protected function encodeClass(): string
    {
        return pack('n', 1);
    }

    protected function encodePreamble(): string
    {
        return implode('', [
            $this->encodeName(),
            $this->encodeType(),
            $this->encodeClass(),
            $this->encodeTtl()
        ]);
    }

    protected function encodeRdata(): string
    {
        return pack('C', strlen($this->rdata)) . $this->rdata;
    }

    final public function __toString(): string
    {
        $rdata = $this->encodeRdata();

        return $this->encodePreamble() . pack('n', strlen($rdata)) . $rdata;
    }
}
