<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Tests\Support\BufferBuilder;
use Reynevan\PhpDnsServer\Zone\Zone;
use Reynevan\PhpDnsServer\Zone\ZoneFileParser;

class ZoneLookupTest extends TestCase
{

    public function testQueryNameIsDecodedAsAnAbsoluteName(): void
    {
        $query = Query::fromBuffer(self::queryPacket('www.example.com', RecordType::A));

        $this->assertSame('www.example.com.', (string) $query->getQuestion()->getName());
        $this->assertSame(RecordType::A, $query->getQuestion()->getType());
    }

    public function testQueryTypeIsReadAfterTheEncodedName(): void
    {
        $query = Query::fromBuffer(self::queryPacket('a.very.long.name.inside.example.com', RecordType::AAAA));

        $this->assertSame('a.very.long.name.inside.example.com.', (string) $query->getQuestion()->getName());
        $this->assertSame(RecordType::AAAA, $query->getQuestion()->getType());
    }

    public function testWireNamesMatchTheFullyQualifiedZoneNames(): void
    {
        $zone = self::zone();

        $result = $zone->lookup(Query::fromBuffer(self::queryPacket('www.example.com', RecordType::A)));

        $this->assertSame(ResponseCode::NOERROR, $result->getRCode());
        $this->assertSame(['192.0.2.10'], self::rdata($result->getRecords()));
    }

    public function testApexIsMatched(): void
    {
        $result = self::zone()->lookup(Query::fromBuffer(self::queryPacket('example.com', RecordType::A)));

        $this->assertSame(ResponseCode::NOERROR, $result->getRCode());
        $this->assertSame(['192.0.2.1'], self::rdata($result->getRecords()));
    }

    public function testLookupIsCaseInsensitive(): void
    {
        $result = self::zone()->lookup(Query::fromBuffer(self::queryPacket('WWW.Example.COM', RecordType::A)));

        $this->assertSame(ResponseCode::NOERROR, $result->getRCode());
        $this->assertSame(['192.0.2.10'], self::rdata($result->getRecords()));
    }

    public function testUnknownNameIsNxdomain(): void
    {
        $result = self::zone()->lookup(Query::fromBuffer(self::queryPacket('nope.example.com', RecordType::A)));

        $this->assertSame(ResponseCode::NXDOMAIN, $result->getRCode());
        $this->assertSame([], $result->getRecords());
    }

    public function testCnameTargetIsAnsweredFullyQualified(): void
    {
        $result = self::zone()->lookup(Query::fromBuffer(self::queryPacket('webmail.example.com', RecordType::CNAME)));

        $this->assertSame(['www.example.com.'], self::rdata($result->getRecords()));
    }

    private static function zone(): Zone
    {
        return (new ZoneFileParser())->parse(implode("\n", [
            '$ORIGIN example.com.',
            '$TTL 3600',
            '@ IN A 192.0.2.1',
            'www IN A 192.0.2.10',
            'webmail IN CNAME www',
        ]));
    }

    /**
     * @param Record[] $records
     * @return string[]
     */
    private static function rdata(array $records): array
    {
        return array_map(fn(Record $record) => $record->getRdata(), $records);
    }

    private static function queryPacket(string $name, RecordType $type): string
    {
        return BufferBuilder::build("\x12\x34", 0x0100, $name, type: $type->toInt());
    }
}
