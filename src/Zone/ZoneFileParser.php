<?php

namespace Reynevan\PhpDnsServer\Zone;

use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordClass;
use Reynevan\PhpDnsServer\Record\RecordType;
use Throwable;

class ZoneFileParser
{
    private const int DEFAULT_TTL = 3600;

    private DomainName $origin;
    private int $ttl = self::DEFAULT_TTL;
    private Zone $zone;

    public function __construct()
    {
        $this->zone = new Zone();
        $this->origin = new DomainName(DomainName::ROOT);
    }

    public function parse(string $file): Zone
    {
        $lines = $this->normalizeLines(explode("\n", $file));

        $previousName = '';
        foreach ($lines as $line) {
            try {
                $name = $this->parseLine($line, $previousName);
            } catch (Throwable $e) {
                echo $e->getMessage();
                continue;
            }
            if ($name) {
                $previousName = $name;
            }
        }

        return $this->zone;
    }

    protected function parseLine(string $line, string $previousName): ?string
    {
        if (strlen(preg_replace('/\s/', '', $line)) === 0) {
            return null;
        }
        if (str_starts_with($line, '$ORIGIN')) {
            $this->origin = DomainName::fromRelative(
                $this->getDirectiveValue('$ORIGIN', $line),
                $this->origin
            );
            return null;
        }
        if (str_starts_with($line, '$TTL')) {
            $this->ttl = (int)$this->getDirectiveValue('$TTL', $line);
            return null;
        }
        $record = $this->parseRecord($line, $previousName);
        if (!$record) {
            return null;
        }
        $this->zone->addRecord($record);
        return $record->getName();
    }

    private function parseRecord(string $line, string $previousName): ?Record
    {
        $parts = explode(' ', $line);
        if (preg_match('/^\s.+/', $line)) {
            $name = $previousName;
        } else {
            $name = $this->parseName(trim(array_shift($parts)));
        }

        $parts = array_values(array_filter($parts, fn ($p) => strlen($p) > 0));
        $ttl = $this->ttl;
        $class = RecordClass::IN;
        $type = null;
        $dataFound = 0;
        $limit = min(count($parts), 3);
        for ($i = 0; $i < $limit; $i++) {
            $upper = mb_strtoupper($parts[$i]);
            if (ctype_digit($parts[$i])) {
                $ttl = (int) $parts[$i];
                $dataFound++;
            } elseif ($recordClass = RecordClass::tryFrom($upper)) {
                $class = $recordClass;
                $dataFound++;
            } elseif ($recordType = RecordType::tryFrom($upper)) {
                $type = $recordType;
                $dataFound++;
                break;
            } else {
                break;
            }
        }
        if ($type === null) {
            return null;
        }
        $rdata = $this->qualifyRdata($type, implode(' ', array_slice($parts, $dataFound)));
        $record = Record::create($type, $class);
        if (!$record) {
            return null;
        }
        $record->setTtl($ttl)->setName($name)->setRdata($rdata);
        return $record;
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private function normalizeLines(array $lines): array
    {
        foreach ($lines as $i => $line) {
            $line = str_replace(["\n", "\r"], '', $line);
            $lines[$i] = $this->removeComment($line);
        }
        $lines = $this->removeEmptyLines($lines);
        return $this->mergeBracketsLines($lines);
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private function removeEmptyLines(array $lines): array
    {
        return array_filter($lines, fn ($line) => strlen(preg_replace('/\s/', '', $line)) !== 0);
    }

    private function removeComment(string $line): string
    {
        if (!str_contains($line, ';')) {
            return $line;
        }

        $quoteOpen = false;
        foreach (str_split($line) as $i => $char) {
            if ($char === '"') {
                $quoteOpen = !$quoteOpen;
            } elseif ($char === ';' && !$quoteOpen) {
                return substr($line, 0, $i);
            }
        }

        return $line;
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private function mergeBracketsLines(array $lines): array
    {
        $bracketOpen = false;
        $bracketLines = [];
        $mergedLines = [];
        foreach ($lines as $line) {
            if (substr_count($line, '(') > substr_count($line, ')')) {
                $bracketOpen = true;
            }
            if ($bracketOpen) {
                $bracketLines[] = $line;
            } else {
                $mergedLines[] = $line;
                continue;
            }
            if (substr_count($line, ')') > substr_count($line, '(')) {
                $bracketOpen = false;
                $mergedLines[] = implode(' ', $bracketLines);
                $bracketLines = [];
            }
        }

        return $mergedLines;
    }

    private function getDirectiveValue(string $directive, string $line): string
    {
        return preg_replace('/\s/', '', str_replace($directive, '', $line));
    }


    private function parseName(string $name): string
    {
        if ($name === '@') {
            return (string) $this->origin;
        }

        return (string) DomainName::fromRelative($name, $this->origin);
    }


    private function qualifyRdata(RecordType $type, string $rdata): string
    {
        return match ($type) {
            RecordType::CNAME,
            RecordType::NS,
            RecordType::PTR => (string) DomainName::fromRelative($rdata, $this->origin),
            RecordType::MX => $this->qualifyToken($rdata, 1),
            RecordType::SOA => $this->qualifyToken($this->qualifyToken($rdata, 0), 1),
            default => $rdata,
        };
    }


    private function qualifyToken(string $rdata, int $index): string
    {
        $parts = preg_split('/(\s+)/', trim($rdata), -1, PREG_SPLIT_DELIM_CAPTURE);
        $position = $index * 2;
        if ($parts === false || !isset($parts[$position]) || $parts[$position] === '') {
            return $rdata;
        }
        $parts[$position] = (string) DomainName::fromRelative($parts[$position], $this->origin);

        return implode('', $parts);
    }
}
