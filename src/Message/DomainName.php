<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;
use Stringable;

readonly class DomainName implements Stringable
{
    public const string ROOT = '.';

    private const int MAX_LABEL_LENGTH = 63;

    private string $name;

    public function __construct(string $name)
    {
        $this->name = self::canonicalize($name);
    }

    public static function fromString(string $name): self
    {
        return new self($name);
    }


    public static function fromRelative(string $name, self|string $origin): self
    {
        $origin = $origin instanceof self ? $origin : new self($origin);
        $name = trim($name);

        if ($name === '' || self::isAbsolute($name)) {
            return $name === '' ? $origin : new self($name);
        }

        return new self($name . '.' . $origin);
    }

    public static function isAbsolute(string $name): bool
    {
        return str_ends_with(trim($name), '.');
    }

    public function encode(): string
    {
        $result = '';
        foreach ($this->getLabels() as $label) {
            $result .= pack('C', strlen($label)) . $label;
        }

        return $result . pack('C', 0);
    }

    /**
     * @throws MalformedQueryException
     */
    public static function decode(string $buffer): self
    {
        $offset = 0;
        $labels = [];
        while (true) {
            if (!isset($buffer[$offset])) {
                throw new MalformedQueryException();
            }
            $length = ord($buffer[$offset]);
            if ($length === 0) {
                break;
            }
            if ($length > self::MAX_LABEL_LENGTH || strlen($buffer) < $offset + 1 + $length) {
                throw new MalformedQueryException();
            }
            $labels[] = substr($buffer, $offset + 1, $length);
            $offset += $length + 1;
        }

        return new self($labels ? implode('.', $labels) . self::ROOT : self::ROOT);
    }


    public function getEncodedLength(): int
    {
        $length = 1;
        foreach ($this->getLabels() as $label) {
            $length += strlen($label) + 1;
        }

        return $length;
    }

    /**
     * @return string[]
     */
    public function getLabels(): array
    {
        if ($this->name === self::ROOT) {
            return [];
        }

        return explode('.', substr($this->name, 0, -1));
    }


    public function equals(self $other): bool
    {
        return strcasecmp($this->name, $other->name) === 0;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    private static function canonicalize(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name === self::ROOT) {
            return self::ROOT;
        }

        return rtrim($name, '.') . self::ROOT;
    }
}
