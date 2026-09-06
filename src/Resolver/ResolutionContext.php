<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Message\Question;

class ResolutionContext
{
    private int $queriesSent = 0;
    private int $cNameHops = 0;
    /**
     * @var array<string, bool>
     */
    private array $nsLookupStack = [];
    /**
     * @var array<string, bool>
     */
    private array $visitedServers = [];

    public function __construct(
        private readonly int $maxQueries = 50,
        private readonly int $maxNsLookupDepth = 10,
        private readonly int $maxCnameHops = 10
    ) {
    }

    public function hasQueryBudget(): bool
    {
        return $this->queriesSent < $this->maxQueries;
    }

    public function spendQuery(): void
    {
        $this->queriesSent++;
    }

    public function canFollowCName(): bool
    {
        return $this->cNameHops < $this->maxCnameHops;
    }

    public function followCName(): void
    {
        $this->cNameHops++;
    }

    public function enterNsLookup(Question $question): bool
    {
        if (count($this->nsLookupStack) >= $this->maxNsLookupDepth) {
            return false;
        }
        $key = self::questionKey($question);
        if (isset($this->nsLookupStack[$key])) {
            return false;
        }
        $this->nsLookupStack[$key] = true;

        return true;
    }

    public function leaveNsLookup(Question $question): void
    {
        unset($this->nsLookupStack[self::questionKey($question)]);
    }

    private static function questionKey(Question $question): string
    {
        return implode('|', [
            strtolower((string) $question->getName()),
            $question->getType()->toInt(),
            $question->getClass()->toInt(),
        ]);
    }

    public function visitServer(string $address, Question $question): bool
    {
        $key = self::serverKey($address, $question);
        if (isset($this->visitedServers[$key])) {
            return false;
        }
        $this->visitedServers[$key] = true;

        return true;
    }

    private static function serverKey(string $address, Question $question): string
    {
        return strtolower($address) . '|' . self::questionKey($question);
    }
}
