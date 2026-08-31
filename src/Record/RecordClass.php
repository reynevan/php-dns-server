<?php

namespace Reynevan\PhpDnsServer\Record;

enum RecordClass: string
{
    case IN = 'IN';
    case CH = 'CH';
    case HS = 'HS';
    case CS = 'CS';

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
     * @return RecordClass[]
     */
    private static function intValues(): array
    {
        return [
            1 => self::IN,
            2 => self::CS,
            3 => self::CH,
            4 => self::HS,
        ];
    }
}
