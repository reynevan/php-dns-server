<?php

namespace Reynevan\PhpDnsServer\Record;

enum RecordType: string
{
    case A = 'A';
    case AAAA = 'AAAA';
    case CAA = 'CAA';
    case CNAME = 'CNAME';
    case MX = 'MX';
    case NS = 'NS';
    case PTR = 'PTR';
    case SOA = 'SOA';
    case TXT = 'TXT';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public static function fromInt(int $type): ?self
    {
        return self::intValues()[$type] ?? null;
    }

    public function toInt(): int
    {
        return array_find_key(self::intValues(), fn($value) => $value === $this);
    }

    /**
     * @return RecordType[]
     */
    private static function intValues(): array
    {
        return [
            1 => self::A,
            2 => self::NS,
            28 => self::AAAA,
            257 => self::CAA,
            5 => self::CNAME,
            15 => self::MX,
            12 => self::PTR,
            6 => self::SOA,
            16 => self::TXT,
        ];
    }
}
