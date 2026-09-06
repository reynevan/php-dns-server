<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Zone\Zone;
use Reynevan\PhpDnsServer\Zone\ZoneFileParser;

class ZoneTest extends TestCase
{
    #[DataProvider('nameProvider')]
    public function testIsAuthoritativeFor(string $name, bool $expected): void
    {
        $this->assertSame($expected, self::zone()->isAuthoritativeFor(DomainName::fromString($name)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function nameProvider(): iterable
    {
        yield 'the apex itself' => ['example.com.', true];
        yield 'a direct child' => ['www.example.com.', true];
        yield 'a name with no records' => ['nonexistent.example.com.', true];
        yield 'a deeply nested child' => ['a.b.c.example.com.', true];
        yield 'a child differing in case' => ['WWW.Example.COM.', true];
        yield 'a relative form of a child' => ['www.example.com', true];

        yield 'another domain under the same tld' => ['google.com.', false];
        yield 'a name merely ending in the apex string' => ['notexample.com.', false];
        yield 'the same label under another tld' => ['example.org.', false];
        yield 'parent zone' => ['com.', false];
        yield 'root' => ['.', false];
        yield 'an unrelated name' => ['4chan.org.', false];
    }

    public function testApexComesFromTheSoaOwnerName(): void
    {
        $this->assertSame('example.com.', (string) self::zone()->getOrigin());
    }

    public function testAZoneWithoutSoaFallsBackToTheFirstOrigin(): void
    {
        $zone = (new ZoneFileParser())->parse(implode("\n", [
            '$ORIGIN example.com.',
            '$TTL 3600',
            'www IN A 192.0.2.1',
            '$ORIGIN later.example.com.',
            'host IN A 192.0.2.2',
        ]));

        $this->assertSame('example.com.', (string) $zone->getOrigin());
        $this->assertTrue($zone->isAuthoritativeFor(DomainName::fromString('host.later.example.com.')));
    }

    public function testARootZoneIsAuthoritativeForEverything(): void
    {
        $zone = (new ZoneFileParser())->parse('. 3600000 NS a.root-servers.net.');

        $this->assertSame('.', (string) $zone->getOrigin());
        $this->assertTrue($zone->isAuthoritativeFor(DomainName::fromString('anything.example.org.')));
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('findByNameProvider')]
    public function testFindByName(string $name, array $expected): void
    {
        $this->assertSame($expected, self::rdata(self::zone()->findByName(DomainName::fromString($name))->toArray()));
    }

    /**
     * @return iterable<string, array{string, string[]}>
     */
    public static function findByNameProvider(): iterable
    {
        yield 'every type owned by the name, in file order' => [
            'www.example.com.',
            ['192.0.2.10', '2001:db8::10'],
        ];
        yield 'repeated records of the same type' => [
            'db.example.com.',
            ['192.0.2.40', '192.0.2.41', '2001:db8::40', '2001:db8::41'],
        ];
        yield 'a name matched case-insensitively' => [
            'WWW.Example.COM.',
            ['192.0.2.10', '2001:db8::10'],
        ];
        yield 'a name written without the trailing dot' => [
            'www.example.com',
            ['192.0.2.10', '2001:db8::10'],
        ];
        yield 'a name below a changed $ORIGIN' => [
            'host1.sub.example.com.',
            ['192.0.2.50', '2001:db8:1::50'],
        ];
        yield 'a name with no records' => ['nonexistent.example.com.', []];
        yield 'a name outside the zone' => ['www.example.org.', []];
        yield 'a parent of an existing name, not itself an owner' => ['sub.example.com.', []];
    }

    public function testFindByNameReturnsAListWithoutGapsInTheKeys(): void
    {
        $records = self::zone()->findByName(DomainName::fromString('archive.example.com.'))->toArray();

        $this->assertSame([0, 1], array_keys($records));
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('findByNameAndTypeProvider')]
    public function testFindByNameAndType(string $name, RecordType $type, array $expected): void
    {
        $records = self::zone()->findByNameAndType(DomainName::fromString($name), $type)->toArray();

        $this->assertSame($expected, self::rdata($records));
    }

    /**
     * @return iterable<string, array{string, RecordType, string[]}>
     */
    public static function findByNameAndTypeProvider(): iterable
    {
        yield 'a single record of the requested type' => [
            'www.example.com.',
            RecordType::A,
            ['192.0.2.10'],
        ];
        yield 'the other type owned by the same name' => [
            'www.example.com.',
            RecordType::AAAA,
            ['2001:db8::10'],
        ];
        yield 'an rrset with several records' => [
            'db.example.com.',
            RecordType::A,
            ['192.0.2.40', '192.0.2.41'],
        ];
        yield 'the apex rrset' => [
            'example.com.',
            RecordType::NS,
            ['ns1.example.com.', 'ns2.example.com.'],
        ];
        yield 'the apex soa' => [
            'example.com.',
            RecordType::SOA,
            ['ns1.example.com. admin.example.com. 2026082901 3600 600 86400 300'],
        ];
        yield 'a name matched case-insensitively' => [
            'DB.EXAMPLE.COM.',
            RecordType::AAAA,
            ['2001:db8::40', '2001:db8::41'],
        ];
        yield 'an existing name that owns no record of that type' => [
            'www.example.com.',
            RecordType::MX,
            [],
        ];
        yield 'a name with no records at all' => ['nonexistent.example.com.', RecordType::A, []];
        yield 'a type owned by another name only' => ['mail.example.com.', RecordType::CNAME, []];
    }

    public function testFindByNameAndTypeDoesNotFollowCnames(): void
    {
        $zone = self::zone();
        $webmail = DomainName::fromString('webmail.example.com.');

        $this->assertSame([], $zone->findByNameAndType($webmail, RecordType::A)->toArray());
        $this->assertSame(
            ['www.example.com.'],
            self::rdata($zone->findByNameAndType($webmail, RecordType::CNAME)->toArray())
        );
    }

    #[DataProvider('hasNameProvider')]
    public function testHasName(string $name, bool $expected): void
    {
        $this->assertSame($expected, self::zone()->hasName(DomainName::fromString($name)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hasNameProvider(): iterable
    {
        yield 'the apex' => ['example.com.', true];
        yield 'a direct child' => ['www.example.com.', true];
        yield 'a name below a changed $ORIGIN' => ['host2.sub.example.com.', true];
        yield 'a name differing in case' => ['Archive.Example.Com.', true];
        yield 'a name written without the trailing dot' => ['archive.example.com', true];
        yield 'a name owning only a cname' => ['imap.example.com.', true];

        yield 'a name with no records inside the zone' => ['nonexistent.example.com.', false];
        yield 'an empty non-terminal' => ['sub.example.com.', false];
        yield 'a child of an existing name' => ['deeper.www.example.com.', false];
        yield 'a name outside the zone' => ['example.org.', false];
    }

    private static function zone(): Zone
    {
        return (new ZoneFileParser())->parse(
            file_get_contents(__DIR__ . '/Fixtures/example.zone')
        );
    }

    /**
     * @param Record[] $records
     * @return string[]
     */
    private static function rdata(array $records): array
    {
        return array_map(fn(Record $record) => $record->getRData(), $records);
    }
}
