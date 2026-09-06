<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;
use Stringable;

readonly class DomainName implements Stringable
{
    public const string ROOT = '.';

    public const string COMPRESSION_POINTER = 'c0';

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
    public static function decode(string $buffer, int &$offset = 0): self
    {
        $labels = [];
        while (true) {
            if (!isset($buffer[$offset])) {
                throw new MalformedQueryException();
            }
            if (self::isCompressed($buffer[$offset])) {
                if (strlen(substr($buffer, $offset, 2)) < 2) {
                    throw new MalformedQueryException();
                }
                $pointer = unpack('n', substr($buffer, $offset, 2))[1] & 0b0011_1111_1111_1111;
                if ($pointer >= $offset) {
                    throw new MalformedQueryException();
                }
                $labels[] = self::decode($buffer, $pointer);
                $offset += 2;
                break;
            } else {
                $length = ord($buffer[$offset]);
                if ($length === 0) {
                    $offset += 1;
                    break;
                }
                if ($length > self::MAX_LABEL_LENGTH) {
                    throw new MalformedQueryException();
                }
                $labels[] = substr($buffer, $offset + 1, $length);
            }
            $offset += $length + 1;
        }

        return new self($labels ? implode('.', $labels) . self::ROOT : self::ROOT);
    }

    public static function isCompressed(string $buffer): bool
    {
        return (ord($buffer) & 0xC0) === 0xC0;
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

    public function isWithin(self $parent): bool
    {
        $parentLabels = $parent->getLabels();
        $ownLabels = $this->getLabels();

        if (count($ownLabels) < count($parentLabels)) {
            return false;
        }

        $tail = array_slice($ownLabels, count($ownLabels) - count($parentLabels));
        foreach ($parentLabels as $i => $label) {
            if (strcasecmp($label, $tail[$i]) !== 0) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return DomainName[]
     */
    public function parents(): array
    {
        $labels = $this->getLabels();
        $result = [];

        for ($i = 0; $i <= count($labels); $i++) {
            $result[] = new self(implode('.', array_slice($labels, $i)) . self::ROOT);
        }

        return $result;
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
