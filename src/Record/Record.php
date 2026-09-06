<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;

class Record
{
    protected DomainName $name;
    protected string $rData;
    protected int $ttl;
    protected RecordClass $class;
    protected RecordType $type;

    private function __construct(?RecordType $type = null, ?RecordClass $class = null)
    {
        if ($type !== null) {
            $this->type = $type;
        }
        if ($class !== null) {
            $this->class = $class;
        }
    }

    public static function create(?RecordType $type, ?RecordClass $class = RecordClass::IN): Record
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
            RecordType::TXT => new TxtRecord(),
            default => null
        };
        if ($record) {
            return $record;
        }
        return new self($type, $class);
    }

    /**
     * @throws MalformedQueryException
     */
    public static function fromBuffer(string $buffer, int &$offset): self
    {
        return self::fromPreamble(RecordPreamble::decode($buffer, $offset), $buffer, $offset);
    }


    public static function fromPreamble(RecordPreamble $preamble, string $buffer, int &$offset): self
    {
        $record = self::create($preamble->getRecordType(), $preamble->getRecordClass());
        $buffer = substr($buffer, 0, $offset + $preamble->getRdLength());
        $record->setName($preamble->getName())
            ->setTtl($preamble->getTtl())
            ->setEncodedRdata($buffer, $offset);

        $offset += $preamble->getRdLength();

        return $record;
    }

    public function getName(): DomainName
    {
        return $this->name;
    }

    public function setName(DomainName $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRData(): string
    {
        return $this->rData;
    }

    public function setRData(string $rData): static
    {
        $this->rData = $rData;

        return $this;
    }

    public function setEncodedRdata(string $buffer, int $offset): static
    {
        $this->rData = substr($buffer, $offset);
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

    public function withTtl(int $ttl): static
    {
        $clone = clone $this;
        $clone->ttl = $ttl;

        return $clone;
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
        return pack('C', strlen($this->rData)) . $this->rData;
    }

    final public function __toString(): string
    {
        $rdata = $this->encodeRdata();

        return $this->encodePreamble() . pack('n', strlen($rdata)) . $rdata;
    }
}
