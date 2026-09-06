<?php

namespace Reynevan\PhpDnsServer\Record;

class TxtRecord extends Record
{
    protected RecordType $type = RecordType::TXT;

    protected function encodeRdata(): string
    {
        $txtLength = pack('c', strlen($this->rData));
        return $txtLength . $this->rData;
    }

    public function setEncodedRdata(string $buffer, int $offset): static
    {
        $txtLength = ord($buffer[$offset++]);
        $this->rData = substr($buffer, $offset, $txtLength);
        return $this;
    }
}
