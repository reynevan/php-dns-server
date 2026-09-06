<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Cache\CacheKey;
use Reynevan\PhpDnsServer\Cache\InMemoryCache;
use Reynevan\PhpDnsServer\Cache\TtlPolicy;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Resolver\ResolutionResult;
use Reynevan\PhpDnsServer\Tests\Support\FakeClock;

class InMemoryCacheTest extends TestCase
{
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FakeClock();
    }

    public function testGetReturnsValuesPassedToSet()
    {
        $result = ResolutionResult::failure(ResponseCode::SERVFAIL);
        $key = self::key('example.com');
        $cache = new InMemoryCache();
        $cache->set($key, $result);
        $cacheResult = $cache->get($key);

        $this->assertEquals($result->getRCode(), $cacheResult->getRCode());
        $this->assertEquals($result->getResolutionType(), $cacheResult->getResolutionType());
    }

    public function testEmptyCacheReturnsNull()
    {
        $this->assertNull($this->cache()->get(self::key('example.com')));
    }

    public function testEvictsOldestEntryWhenFull(): void
    {
        $cache = $this->cache(maxEntries: 2);
        foreach (['a', 'b', 'c'] as $host) {
            $cache->set(self::key("$host.example.com"), ResolutionResult::answer(
                new RecordSet([self::a("$host.example.com", 300)])
            ));
        }

        $this->assertNull($cache->get(self::key('a.example.com')));
        $this->assertNotNull($cache->get(self::key('b.example.com')));
        $this->assertNotNull($cache->get(self::key('c.example.com')));
    }

    public function testCachedAnswerIsNeverAuthoritative(): void
    {
        $cache = $this->cache();
        $cache->set(self::key('example.com'), ResolutionResult::answer(
            new RecordSet([self::a('example.com', 300)]),
            authoritative: true
        ));

        $this->assertFalse($cache->get(self::key('example.com'))?->isAuthoritative());
    }

    public function testKeyIsCaseInsensitive(): void
    {
        $cache = $this->cache();
        $cache->set(self::key('EXAMPLE.COM'), ResolutionResult::answer(
            new RecordSet([self::a('example.com', 300)])
        ));

        $this->assertNotNull($cache->get(self::key('example.com')));
    }

    public function testEntryExpiresAtItsTtl(): void
    {
        $cache = $this->cache();
        $cache->set(self::key('example.com'), ResolutionResult::answer(
            new RecordSet([self::a('example.com', 300)])
        ));

        $this->clock->advance(300);

        $this->assertNull($cache->get(self::key('example.com')));
    }

    private function cache(?TtlPolicy $policy = null, int $maxEntries = 10_000): InMemoryCache
    {
        return new InMemoryCache($policy ?? new TtlPolicy(), $this->clock, $maxEntries);
    }

    private static function key(string $name, RecordType $type = RecordType::A): CacheKey
    {
        return new CacheKey(DomainName::fromString($name), $type);
    }

    private static function a(string $name, int $ttl, string $ip = '192.0.2.1'): Record
    {
        return (Record::create(RecordType::A))->setName(DomainName::fromString($name))->setTtl($ttl)->setRData($ip);
    }
}
