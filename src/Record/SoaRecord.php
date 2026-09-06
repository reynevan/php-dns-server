<?php

namespace Reynevan\PhpDnsServer\Record;

use Reynevan\PhpDnsServer\Message\DomainName;

class SoaRecord extends Record
{
    protected RecordType $type = RecordType::SOA;

    protected function encodeRdata(): string
    {
        $data = str_replace(['(', ')'], '', $this->rData);
        $data = preg_replace('/\s+/', ' ', $data);
        $data = trim($data);
        $parts = explode(' ', $data);
        $mName = array_shift($parts);
        $rName = array_shift($parts);
        return implode('', [
            (DomainName::fromString($mName))->encode(),
            (DomainName::fromString($rName))->encode(),
            pack('NNNNN', ...$parts),
        ]);
    }

    public function setEncodedRdata(string $buffer, int $offset): static
    {
        $mName = DomainName::decode($buffer, $offset);
        $rName = DomainName::decode($buffer, $offset);
        $fields = unpack('Nserial/Nrefresh/Nretry/Nexpire/Nttl', substr($buffer, $offset, 20));

        $this->rData = implode(' ', [
            $mName,
            $rName,
            $fields['serial'],
            $fields['refresh'],
            $fields['retry'],
            $fields['expire'],
            $fields['ttl']
        ]);
        return $this;
    }

    public function getMinimum(): int
    {
        return (int) explode(' ', $this->rData)[6];
    }
}
