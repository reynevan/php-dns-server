<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Zone\Zone;
use Reynevan\PhpDnsServer\Zone\ZoneFileParser;

class ZoneFileParserTest extends TestCase
{
    /**
     * @param list<array{string, string, int, string}> $expected
     */
    #[DataProvider('zoneSnippetProvider')]
    public function testParsesZoneSnippet(string $zone, array $expected): void
    {
        $this->assertSame($expected, self::toArray((new ZoneFileParser())->parse($zone)));
    }

    public function testParsesFullExampleZone(): void
    {
        $zone = (new ZoneFileParser())->parse(
            file_get_contents(__DIR__ . '/Fixtures/example.zone')
        );

        $this->assertSame([
            ['example.com.', 'SOA', 3600, 'ns1.example.com. admin.example.com. 2026082901 3600 600 86400 300'],
            ['example.com.', 'NS', 3600, 'ns1.example.com.'],
            ['example.com.', 'NS', 3600, 'ns2.example.com.'],
            ['www.example.com.', 'A', 3600, '192.0.2.10'],
            ['mail.example.com.', 'A', 3600, '192.0.2.20'],
            ['ftp.example.com.', 'A', 7200, '192.0.2.30'],
            ['db.example.com.', 'A', 1800, '192.0.2.40'],
            ['db.example.com.', 'A', 3600, '192.0.2.41'],
            ['example.com.', 'A', 3600, '192.0.2.1'],
            ['www.example.com.', 'AAAA', 3600, '2001:db8::10'],
            ['mail.example.com.', 'AAAA', 3600, '2001:db8::20'],
            ['ftp.example.com.', 'AAAA', 7200, '2001:db8::30'],
            ['db.example.com.', 'AAAA', 1800, '2001:db8::40'],
            ['db.example.com.', 'AAAA', 3600, '2001:db8::41'],
            ['example.com.', 'AAAA', 3600, '2001:0db8:0000:0000:0000:0000:0000:0001'],
            ['ipv6.example.com.', 'AAAA', 3600, '2001:DB8::AB:CDEF'],
            ['example.com.', 'MX', 3600, '10 mail.example.com.'],
            ['example.com.', 'MX', 3600, '20 mail2.example.com.'],
            ['webmail.example.com.', 'CNAME', 3600, 'www.example.com.'],
            ['imap.example.com.', 'CNAME', 3600, 'mail.example.com.'],
            ['example.com.', 'TXT', 3600, '"v=spf1 mx a ip4:192.0.2.0/24 -all"'],
            ['_dmarc.example.com.', 'TXT', 3600, '"v=DMARC1; " "p=none; rua=mailto:dmarc@example.com"'],
            ['example.com.', 'CAA', 3600, '0 issue "letsencrypt.org"'],
            ['alias.example.com.', 'PTR', 3600, 'www.example.com.'],
            ['host1.sub.example.com.', 'A', 3600, '192.0.2.50'],
            ['host2.sub.example.com.', 'A', 3600, '192.0.2.51'],
            ['host1.sub.example.com.', 'AAAA', 3600, '2001:db8:1::50'],
            ['archive.example.com.', 'A', 86400, '192.0.2.60'],
            ['archive.example.com.', 'AAAA', 86400, '2001:db8::60'],
            ['lowercase.example.com.', 'A', 86400, '192.0.2.70'],
        ], self::toArray($zone));
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int, string}>}>
     */
    public static function zoneSnippetProvider(): iterable
    {
        yield 'relative name is qualified with $ORIGIN' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            www IN A 192.0.2.10
            ZONE,
            [['www.example.com.', 'A', 3600, '192.0.2.10']],
        ];

        yield 'absolute name keeps its own labels' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            www.other.test. IN A 192.0.2.10
            ZONE,
            [['www.other.test.', 'A', 3600, '192.0.2.10']],
        ];

        yield '@ resolves to the zone apex' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            @ IN A 192.0.2.1
            ZONE,
            [['example.com.', 'A', 3600, '192.0.2.1']],
        ];

        yield 'ttl and class are optional' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            www A 192.0.2.10
            ZONE,
            [['www.example.com.', 'A', 3600, '192.0.2.10']],
        ];

        yield 'explicit ttl overrides $TTL' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            www 7200 A 192.0.2.10
            ZONE,
            [['www.example.com.', 'A', 7200, '192.0.2.10']],
        ];

        yield 'ttl and class in either order' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            a IN 1800 A 192.0.2.1
            b 1800 IN A 192.0.2.2
            ZONE,
            [
                ['a.example.com.', 'A', 1800, '192.0.2.1'],
                ['b.example.com.', 'A', 1800, '192.0.2.2'],
            ],
        ];

        yield 'omitted name inherits the previous record name' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            db IN A 192.0.2.40
                IN A 192.0.2.41
            ZONE,
            [
                ['db.example.com.', 'A', 3600, '192.0.2.40'],
                ['db.example.com.', 'A', 3600, '192.0.2.41'],
            ],
        ];

        yield 'comments and blank lines are ignored' => [
            <<<ZONE
            ; leading comment
            \$ORIGIN example.com.
            \$TTL 3600

            www IN A 192.0.2.10 ; trailing comment

            ; another comment
            ZONE,
            [['www.example.com.', 'A', 3600, '192.0.2.10']],
        ];

        yield '$ORIGIN and $TTL apply from the line they appear on' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            a IN A 192.0.2.1
            \$ORIGIN sub.example.com.
            \$TTL 86400
            b IN A 192.0.2.2
            ZONE,
            [
                ['a.example.com.', 'A', 3600, '192.0.2.1'],
                ['b.sub.example.com.', 'A', 86400, '192.0.2.2'],
            ],
        ];

        yield 'type and class are case insensitive' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            www in a 192.0.2.10
            ZONE,
            [['www.example.com.', 'A', 3600, '192.0.2.10']],
        ];

        yield 'quoted rdata keeps its spaces' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            @ IN TXT "v=spf1 mx a -all"
            ZONE,
            [['example.com.', 'TXT', 3600, '"v=spf1 mx a -all"']],
        ];

        yield 'ipv6 address in rdata is not split or mistaken for a ttl' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            www IN AAAA 2001:db8::10
            ZONE,
            [['www.example.com.', 'AAAA', 3600, '2001:db8::10']],
        ];

        yield 'names are stored fully qualified, with the trailing dot' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            @ IN A 192.0.2.1
            www IN A 192.0.2.10
            ftp.example.com. IN A 192.0.2.30
            ZONE,
            [
                ['example.com.', 'A', 3600, '192.0.2.1'],
                ['www.example.com.', 'A', 3600, '192.0.2.10'],
                ['ftp.example.com.', 'A', 3600, '192.0.2.30'],
            ],
        ];

        yield '$ORIGIN without a trailing dot still yields absolute names' => [
            <<<ZONE
            \$ORIGIN example.com
            \$TTL 3600
            @ IN A 192.0.2.1
            www IN A 192.0.2.10
            ZONE,
            [
                ['example.com.', 'A', 3600, '192.0.2.1'],
                ['www.example.com.', 'A', 3600, '192.0.2.10'],
            ],
        ];

        yield 'relative $ORIGIN is resolved against the current one' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            \$ORIGIN sub
            www IN A 192.0.2.10
            ZONE,
            [['www.sub.example.com.', 'A', 3600, '192.0.2.10']],
        ];

        yield 'relative rdata targets are qualified with $ORIGIN' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            webmail IN CNAME www
            @ IN NS ns1
            @ IN MX 10 mail
            alias IN PTR www
            ZONE,
            [
                ['webmail.example.com.', 'CNAME', 3600, 'www.example.com.'],
                ['example.com.', 'NS', 3600, 'ns1.example.com.'],
                ['example.com.', 'MX', 3600, '10 mail.example.com.'],
                ['alias.example.com.', 'PTR', 3600, 'www.example.com.'],
            ],
        ];

        yield 'absolute rdata targets keep their own labels' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            webmail IN CNAME www.other.test.
            @ IN NS ns1.other.test.
            @ IN MX 10 mail.other.test.
            alias IN PTR www.other.test.
            ZONE,
            [
                ['webmail.example.com.', 'CNAME', 3600, 'www.other.test.'],
                ['example.com.', 'NS', 3600, 'ns1.other.test.'],
                ['example.com.', 'MX', 3600, '10 mail.other.test.'],
                ['alias.example.com.', 'PTR', 3600, 'www.other.test.'],
            ],
        ];

        yield 'soa mname and rname are qualified, the numbers are untouched' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            @ IN SOA ns1 admin 2026082901 3600 600 86400 300
            ZONE,
            [[
                'example.com.',
                'SOA',
                3600,
                'ns1.example.com. admin.example.com. 2026082901 3600 600 86400 300',
            ]],
        ];

        yield 'multi-line soa keeps its qualified names' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            @ IN SOA ns1 admin (
                2026082901
                3600
                600
                86400
                300 )
            ZONE,
            [[
                'example.com.',
                'SOA',
                3600,
                'ns1.example.com. admin.example.com. ( 2026082901 3600 600 86400 300 )',
            ]],
        ];

        yield 'mx priority is rdata, not a ttl' => [
            <<<ZONE
            \$ORIGIN example.com.
            \$TTL 3600
            @ IN MX 10 mail.example.com.
            ZONE,
            [['example.com.', 'MX', 3600, '10 mail.example.com.']],
        ];
    }

    /**
     * @param list<array{string, string, int, string}> $expected
     */
    #[DataProvider('recordTypeProvider')]
    public function testParsesRecordType(string $zone, array $expected): void
    {
        $this->assertSame($expected, self::toArray((new ZoneFileParser())->parse($zone)));
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int, string}>}>
     */
    public static function recordTypeProvider(): iterable
    {
        yield 'SOA' => [
            self::zone('@ 3600 IN SOA ns1.example.com. admin.example.com. 2026082901 3600 600 86400 300'),
            [[
                'example.com.',
                'SOA',
                3600,
                'ns1.example.com. admin.example.com. 2026082901 3600 600 86400 300',
            ]],
        ];

        yield 'NS' => [
            self::zone(
                '@ IN NS ns1.example.com.',
                '  IN NS ns2.example.com.',
            ),
            [
                ['example.com.', 'NS', 3600, 'ns1.example.com.'],
                ['example.com.', 'NS', 3600, 'ns2.example.com.'],
            ],
        ];

        yield 'A' => [
            self::zone('www IN A 192.0.2.10'),
            [['www.example.com.', 'A', 3600, '192.0.2.10']],
        ];

        yield 'AAAA' => [
            self::zone('www IN AAAA 2001:db8::10'),
            [['www.example.com.', 'AAAA', 3600, '2001:db8::10']],
        ];

        yield 'AAAA in full (uncompressed) form' => [
            self::zone('@ IN AAAA 2001:0db8:0000:0000:0000:0000:0000:0001'),
            [['example.com.', 'AAAA', 3600, '2001:0db8:0000:0000:0000:0000:0000:0001']],
        ];

        yield 'AAAA with uppercase hex digits and lowercase type' => [
            self::zone('ipv6 in aaaa 2001:DB8::AB:CDEF'),
            [['ipv6.example.com.', 'AAAA', 3600, '2001:DB8::AB:CDEF']],
        ];

        yield 'AAAA alongside A for the same name' => [
            self::zone(
                'db IN A 192.0.2.40',
                '   IN AAAA 2001:db8::40',
            ),
            [
                ['db.example.com.', 'A', 3600, '192.0.2.40'],
                ['db.example.com.', 'AAAA', 3600, '2001:db8::40'],
            ],
        ];

        yield 'AAAA with an explicit ttl' => [
            self::zone('ftp 7200 AAAA 2001:db8::30'),
            [['ftp.example.com.', 'AAAA', 7200, '2001:db8::30']],
        ];

        yield 'MX' => [
            self::zone(
                '@ IN MX 10 mail.example.com.',
                '@ IN MX 20 mail2.example.com.',
            ),
            [
                ['example.com.', 'MX', 3600, '10 mail.example.com.'],
                ['example.com.', 'MX', 3600, '20 mail2.example.com.'],
            ],
        ];

        yield 'CNAME' => [
            self::zone(
                'webmail IN CNAME www',
                'imap IN CNAME mail.example.com.',
            ),
            [
                ['webmail.example.com.', 'CNAME', 3600, 'www.example.com.'],
                ['imap.example.com.', 'CNAME', 3600, 'mail.example.com.'],
            ],
        ];

        yield 'TXT' => [
            self::zone('@ IN TXT "v=spf1 mx a ip4:192.0.2.0/24 -all"'),
            [['example.com.', 'TXT', 3600, '"v=spf1 mx a ip4:192.0.2.0/24 -all"']],
        ];

        yield 'TXT with multiple quoted strings and a semicolon inside' => [
            self::zone('_dmarc IN TXT "v=DMARC1; " "p=none; rua=mailto:dmarc@example.com"'),
            [[
                '_dmarc.example.com.',
                'TXT',
                3600,
                '"v=DMARC1; " "p=none; rua=mailto:dmarc@example.com"',
            ]],
        ];

        yield 'CAA' => [
            self::zone('@ IN CAA 0 issue "letsencrypt.org"'),
            [['example.com.', 'CAA', 3600, '0 issue "letsencrypt.org"']],
        ];

        yield 'PTR' => [
            self::zone('alias IN PTR www.example.com.'),
            [['alias.example.com.', 'PTR', 3600, 'www.example.com.']],
        ];
    }

    /**
     * Wraps record lines in the $ORIGIN/$TTL preamble shared by every type case.
     */
    private static function zone(string ...$lines): string
    {
        return implode("\n", ['$ORIGIN example.com.', '$TTL 3600', ...$lines]);
    }

    /**
     * @return list<array{string, string, int, string}>
     */
    private static function toArray(Zone $zone): array
    {
        return array_map(
            fn(Record $record) => [
                $record->getName(),
                $record->getType()->value,
                $record->getTtl(),
                $record->getRdata(),
            ],
            $zone->getRecords()
        );
    }
}
