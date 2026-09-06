<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Resolver\NameServer;
use Reynevan\PhpDnsServer\Resolver\NameServerSet;

class NameServerSetTest extends TestCase
{
    public function testNextReturnEveryServer(): void
    {
        $nsSet = self::nsSet();
        $ns = $nsSet->next();
        $this->assertServer('a-dns.com', ['192.168.0.1', '192.168.1.1'], $ns);
        $ns = $nsSet->next();
        $this->assertServer('b-dns.com', ['192.168.0.2'], $ns);
        $ns = $nsSet->next();
        $this->assertServer('c-dns.com', ['192.168.0.3'], $ns);
    }

    public function testNextResetsAfterNameServersEnd(): void
    {
        $nsSet = self::nsSet();
        $nsSet->next();
        $nsSet->next();
        $nsSet->next();
        $this->assertServer('a-dns.com', ['192.168.0.1', '192.168.1.1'], $nsSet->next());
    }

    public function testNameServerSetDoesntReturnDeadServer(): void
    {
        $nsSet = self::nsSet();
        $ns = $nsSet->next();
        $nsSet->markDead($ns);
        $names = [];
        for ($i = 0; $i < 4; $i++) {
            $names[] = (string) $nsSet->next()->getName();
        }
        $this->assertSame(['b-dns.com.', 'c-dns.com.', 'b-dns.com.', 'c-dns.com.'], $names);
    }

    public function testNameServerSetReturnsNullWhenNoNameServers(): void
    {
        $nsSet = NameServerSet::fromRecords(
            new RecordSet([]),
            new RecordSet([])
        );
        $this->assertNull($nsSet->next());
    }

    public function testFromRecordIgnoresIncorrectTypes(): void
    {
        $nsSet = NameServerSet::fromRecords(
            new RecordSet([
                self::record(RecordType::TXT, 'com', 'a-dns.com'),
                self::ns('com', 'b-dns.com'),
                self::ns('com', 'c-dns.com'),
            ]),
            new RecordSet([
                self::a('a-dns.com', '192.168.0.1'),
                self::record(RecordType::MX, 'b-dns.com', '192.168.0.2'),
                self::a('c-dns.com', '192.168.0.3')
            ])
        );
        $ns = $nsSet->next();
        $this->assertServer('b-dns.com', [], $ns);
        $ns = $nsSet->next();
        $this->assertServer('c-dns.com', ['192.168.0.3'], $ns);
    }

    public static function nsSet(): NameServerSet
    {
        return NameServerSet::fromRecords(
            new RecordSet([
                self::ns('com', 'a-dns.com'),
                self::ns('com', 'b-dns.com'),
                self::ns('com', 'c-dns.com'),
            ]),
            new RecordSet([
                self::a('a-dns.com', '192.168.0.1'),
                self::a('a-dns.com', '192.168.1.1'),
                self::a('b-dns.com', '192.168.0.2'),
                self::a('c-dns.com', '192.168.0.3')
            ])
        );
    }

    public static function record(RecordType $type, string $name, string $data): Record
    {
        $record = Record::create($type);
        $record
            ->setRData($data)
            ->setName(DomainName::fromString($name));
        return $record;
    }

    public static function a(string $name, string $ip): Record
    {
        return self::record(RecordType::A, $name, $ip);
    }

    public static function ns(string $name, string $nameServer): Record
    {
        return self::record(RecordType::NS, $name, $nameServer);
    }

    private static function assertServer(string $name, array $addressesA, ?NameServer $ns): void
    {
        self::assertNotNull($ns);
        self::assertEquals(DomainName::fromString($name), $ns->getName());
        self::assertSame($addressesA, $ns->getAddressA());
    }
}
