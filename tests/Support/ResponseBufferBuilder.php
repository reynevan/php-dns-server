<?php

namespace Reynevan\PhpDnsServer\Tests\Support;

use InvalidArgumentException;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Record\RecordClass;
use Reynevan\PhpDnsServer\Record\RecordType;

/**
 * Builds a whole response packet, byte for byte the way an upstream server answers: header, the echoed
 * question, then the ANSWER / AUTHORITY / ADDITIONAL sections. Names are compressed against every name
 * already written to the packet (RFC 1035 4.1.4), owner names and names inside RDATA alike, because that
 * is what a real server sends and a parser that ignores pointers must fail these fixtures.
 *
 * A referral, the way a root server answers a query it does not own:
 *
 *     ResponseBufferBuilder::create()
 *         ->withFlags(ResponseBufferBuilder::FLAGS_REFERRAL)
 *         ->withQuestion('www.example.com.', RecordType::A)
 *         ->withAuthority('com.', RecordType::NS, 'a.gtld-servers.net.', 172800)
 *         ->withAdditional('a.gtld-servers.net.', RecordType::A, '192.5.6.30', 172800)
 *         ->build();
 *
 * RDATA is written in zone-file (presentation) form, the same syntax ZoneFileParser reads. For a type
 * RecordType does not know, pass the numeric type and RDATA as raw bytes.
 */
class ResponseBufferBuilder
{
    /** QR, RD, RA, NOERROR - an ordinary recursive answer. */
    public const int FLAGS_ANSWER = 0x8180;

    /** QR, AA, RD, RA, NOERROR - the zone's own server answering. */
    public const int FLAGS_AUTHORITATIVE = 0x8580;

    /** QR, RD, NOERROR, no AA and no RA - a root/TLD server handing out a delegation. */
    public const int FLAGS_REFERRAL = 0x8100;

    /** QR, AA, RD, RA, NXDOMAIN. */
    public const int FLAGS_NXDOMAIN = 0x8583;

    /** QR, RD, RA, SERVFAIL. */
    public const int FLAGS_SERVFAIL = 0x8182;

    private const int HEADER_LENGTH = 12;

    private const int POINTER_MASK = 0xC000;

    /** Offsets past 14 bits cannot be pointed at, so names living there are written out in full. */
    private const int MAX_POINTER = 0x3FFF;

    private const int MAX_CHARACTER_STRING = 255;

    private const int OPT_TYPE = 41;

    private int $flags = self::FLAGS_ANSWER;

    /** @var list<array{name: string, type: int, class: int}> */
    private array $questions = [];

    /** @var list<array{name: string, type: int, class: int, ttl: int, rdata: string}> */
    private array $answers = [];

    /** @var list<array{name: string, type: int, class: int, ttl: int, rdata: string}> */
    private array $authority = [];

    /** @var list<array{name: string, type: int, class: int, ttl: int, rdata: string}> */
    private array $additional = [];

    /** @var array{0: ?int, 1: ?int, 2: ?int, 3: ?int} counts forced into the header, for malformed packets */
    private array $counts = [null, null, null, null];

    private bool $compress = true;

    /** @var array<string, int> name (lowercased, absolute) => offset it was first written at */
    private array $offsets = [];

    private function __construct(private string $txId)
    {
    }

    public static function create(string|int|null $txId = null): self
    {
        return new self(self::encodeTxId($txId ?? random_bytes(2)));
    }

    public function withTxId(string|int $txId): self
    {
        $this->txId = self::encodeTxId($txId);

        return $this;
    }

    public function withFlags(int $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function withQuestion(
        string $name = 'example.com.',
        RecordType|int $type = RecordType::A,
        RecordClass|int $class = RecordClass::IN
    ): self {
        $this->questions[] = [
            'name' => $name,
            'type' => self::typeToInt($type),
            'class' => self::classToInt($class),
        ];

        return $this;
    }

    public function withAnswer(
        string $name,
        RecordType|int $type,
        string $rdata,
        int $ttl = 3600,
        RecordClass|int $class = RecordClass::IN
    ): self {
        $this->answers[] = self::record($name, $type, $rdata, $ttl, $class);

        return $this;
    }

    public function withAuthority(
        string $name,
        RecordType|int $type,
        string $rdata,
        int $ttl = 3600,
        RecordClass|int $class = RecordClass::IN
    ): self {
        $this->authority[] = self::record($name, $type, $rdata, $ttl, $class);

        return $this;
    }

    public function withAdditional(
        string $name,
        RecordType|int $type,
        string $rdata,
        int $ttl = 3600,
        RecordClass|int $class = RecordClass::IN
    ): self {
        $this->additional[] = self::record($name, $type, $rdata, $ttl, $class);

        return $this;
    }

    /**
     * The OPT pseudo-record every EDNS(0) capable server puts in ADDITIONAL: root owner name, the UDP
     * payload size in the class field and the extended rcode / version / DO bit in the TTL field.
     */
    public function withOpt(
        int $payloadSize = 1232,
        int $extendedRCode = 0,
        int $version = 0,
        bool $dnssecOk = false
    ): self {
        $this->additional[] = [
            'name' => DomainName::ROOT,
            'type' => self::OPT_TYPE,
            'class' => $payloadSize,
            'ttl' => ($extendedRCode << 24) | ($version << 16) | (($dnssecOk ? 1 : 0) << 15),
            'rdata' => '',
        ];

        return $this;
    }

    /**
     * Forces header counts that disagree with the sections actually written - the only way to build the
     * truncated and lying packets a resolver has to survive. Null keeps the real count.
     */
    public function withCounts(
        ?int $questions = null,
        ?int $answers = null,
        ?int $authority = null,
        ?int $additional = null
    ): self {
        $this->counts = [$questions, $answers, $authority, $additional];

        return $this;
    }

    /**
     * Writes every name in full. Legal, and what a minimal server sends, but no real resolver's fixtures
     * should stop here - keep at least one compressed case.
     */
    public function withoutCompression(): self
    {
        $this->compress = false;

        return $this;
    }

    public function build(): string
    {
        $this->offsets = [];
        $body = '';

        foreach ($this->questions as $question) {
            $body .= $this->encodeName($question['name'], self::HEADER_LENGTH + strlen($body))
                . pack('nn', $question['type'], $question['class']);
        }

        foreach ([$this->answers, $this->authority, $this->additional] as $section) {
            foreach ($section as $record) {
                $body .= $this->encodeRecord($record, self::HEADER_LENGTH + strlen($body));
            }
        }

        return implode('', [
            $this->txId,
            pack('n', $this->flags),
            pack('n', $this->counts[0] ?? count($this->questions)),
            pack('n', $this->counts[1] ?? count($this->answers)),
            pack('n', $this->counts[2] ?? count($this->authority)),
            pack('n', $this->counts[3] ?? count($this->additional)),
            $body,
        ]);
    }

    /**
     * @param array{name: string, type: int, class: int, ttl: int, rdata: string} $record
     */
    private function encodeRecord(array $record, int $offset): string
    {
        $preamble = $this->encodeName($record['name'], $offset)
            . pack('nnN', $record['type'], $record['class'], $record['ttl']);

        // RDLENGTH sits between the preamble and RDATA, so names inside RDATA start two bytes further on.
        $rdata = $this->encodeRdata($record['type'], $record['rdata'], $offset + strlen($preamble) + 2);

        return $preamble . pack('n', strlen($rdata)) . $rdata;
    }

    private function encodeRdata(int $type, string $rdata, int $offset): string
    {
        return match (RecordType::fromInt($type)) {
            RecordType::A => self::encodeAddress($rdata, 4),
            RecordType::AAAA => self::encodeAddress($rdata, 16),
            RecordType::NS, RecordType::CNAME, RecordType::PTR => $this->encodeName($rdata, $offset),
            RecordType::MX => $this->encodeMx($rdata, $offset),
            RecordType::SOA => $this->encodeSoa($rdata, $offset),
            RecordType::TXT => self::encodeTxt($rdata),
            RecordType::CAA => self::encodeCaa($rdata),
            default => $rdata,
        };
    }

    /**
     * Emits the name, pointing at the longest suffix already present in the packet. Every suffix written
     * out in full is remembered at its own offset, which is how one name teaches the next one to be short.
     */
    private function encodeName(string $name, int $offset): string
    {
        $labels = DomainName::fromString($name)->getLabels();
        $result = '';

        while ($labels !== []) {
            $suffix = strtolower(implode('.', $labels)) . DomainName::ROOT;
            if ($this->compress && isset($this->offsets[$suffix])) {
                return $result . pack('n', self::POINTER_MASK | $this->offsets[$suffix]);
            }
            if ($this->compress && $offset <= self::MAX_POINTER) {
                $this->offsets[$suffix] = $offset;
            }

            $label = array_shift($labels);
            $result .= pack('C', strlen($label)) . $label;
            $offset += strlen($label) + 1;
        }

        return $result . pack('C', 0);
    }

    private function encodeMx(string $rdata, int $offset): string
    {
        [$preference, $exchange] = self::fields($rdata, 2, 'MX', '<preference> <exchange>');

        return pack('n', (int) $preference) . $this->encodeName($exchange, $offset + 2);
    }

    private function encodeSoa(string $rdata, int $offset): string
    {
        [$mName, $rName, $serial, $refresh, $retry, $expire, $minimum] = self::fields(
            str_replace(['(', ')'], ' ', $rdata),
            7,
            'SOA',
            '<mname> <rname> <serial> <refresh> <retry> <expire> <minimum>'
        );

        $mNameEncoded = $this->encodeName($mName, $offset);

        return $mNameEncoded
            . $this->encodeName($rName, $offset + strlen($mNameEncoded))
            . pack('NNNNN', (int) $serial, (int) $refresh, (int) $retry, (int) $expire, (int) $minimum);
    }

    private static function encodeAddress(string $rdata, int $length): string
    {
        $binary = @inet_pton(trim($rdata));

        if ($binary === false || strlen($binary) !== $length) {
            throw new InvalidArgumentException(
                sprintf('Invalid IPv%d address: %s', $length === 4 ? 4 : 6, $rdata)
            );
        }

        return $binary;
    }

    /** TXT RDATA is a sequence of character-strings, each at most 255 bytes long. */
    private static function encodeTxt(string $rdata): string
    {
        $text = str_replace('"', '', $rdata);
        $result = '';

        foreach (str_split($text, self::MAX_CHARACTER_STRING) ?: [''] as $chunk) {
            $result .= pack('C', strlen($chunk)) . $chunk;
        }

        return $result;
    }

    private static function encodeCaa(string $rdata): string
    {
        [$flag, $tag, $value] = self::fields($rdata, 3, 'CAA', '<flag> <tag> <value>');

        return pack('CC', (int) $flag, strlen($tag)) . $tag . str_replace('"', '', $value);
    }

    /**
     * @return array{name: string, type: int, class: int, ttl: int, rdata: string}
     */
    private static function record(
        string $name,
        RecordType|int $type,
        string $rdata,
        int $ttl,
        RecordClass|int $class
    ): array {
        return [
            'name' => $name,
            'type' => self::typeToInt($type),
            'class' => self::classToInt($class),
            'ttl' => $ttl,
            'rdata' => $rdata,
        ];
    }

    /**
     * Splits presentation RDATA into exactly $count fields, the last one keeping any remaining spaces.
     *
     * @return list<string>
     */
    private static function fields(string $rdata, int $count, string $type, string $syntax): array
    {
        $fields = preg_split('/\s+/', trim($rdata), $count, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($fields) !== $count) {
            throw new InvalidArgumentException(
                sprintf('Malformed %s RDATA, expected "%s": %s', $type, $syntax, $rdata)
            );
        }

        return $fields;
    }

    private static function encodeTxId(string|int $txId): string
    {
        return is_int($txId) ? pack('n', $txId) : str_pad(substr($txId, 0, 2), 2, "\0");
    }

    private static function typeToInt(RecordType|int $type): int
    {
        return $type instanceof RecordType ? $type->toInt() : $type;
    }

    private static function classToInt(RecordClass|int $class): int
    {
        return $class instanceof RecordClass ? $class->toInt() : $class;
    }
}
