<?php

namespace Reynevan\PhpDnsServer\Record;

class CaaRecord extends Record
{
    protected RecordType $type = RecordType::CAA;

    protected function encodeRdata(): string
    {
        $parts = explode(' ', $this->rdata, 3);
        $flag = array_shift($parts);
        $tag = array_shift($parts);
        return pack('c', $flag) . pack('c', strlen($tag)) . $tag . str_replace('"', '', implode(' ', $parts));
    }
}
