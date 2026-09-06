<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;

class DomainNameTest extends TestCase
{
    /**
     * A response-shaped packet: a twelve-byte header, one uncompressed name and three
     * compressed ones, laid out the way a real answer section reuses earlier names.
     *
     *   12  \x03www\x07example\x03com\x00   www.example.com.   (17 bytes, ends at 28)
     *   29  \xc0\x0c                        -> 12              www.example.com.
     *   31  \x04mail\xc0\x10                mail + -> 16       mail.example.com.
     *   38  \xc0\x1d                        -> 29, a pointer   www.example.com.
     *   40  \xc0\x1c                        -> 28, the root    .
     */
    private const string PACKET = "\x2a\x2a\x81\x80\x00\x01\x00\x03\x00\x00\x00\x00"
        . "\x03www\x07example\x03com\x00"
        . "\xc0\x0c"
        . "\x04mail\xc0\x10"
        . "\xc0\x1d"
        . "\xc0\x1c";

    #[DataProvider('canonicalizationProvider')]
    public function testCanonicalizesToAnAbsoluteName(string $input, string $expected): void
    {
        $this->assertSame($expected, (string) DomainName::fromString($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function canonicalizationProvider(): iterable
    {
        yield 'relative name gets the root dot' => ['www.example.com', 'www.example.com.'];
        yield 'absolute name is left alone' => ['www.example.com.', 'www.example.com.'];
        yield 'surrounding whitespace is trimmed' => ["  www.example.com \t", 'www.example.com.'];
        yield 'repeated trailing dots collapse' => ['www.example.com..', 'www.example.com.'];
        yield 'empty name is the root' => ['', '.'];
        yield 'root stays the root' => ['.', '.'];
    }

    #[DataProvider('relativeProvider')]
    public function testQualifiesRelativeNamesWithTheOrigin(
        string $name,
        string $origin,
        string $expected
    ): void {
        $this->assertSame($expected, (string) DomainName::fromRelative($name, $origin));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function relativeProvider(): iterable
    {
        yield 'relative name is qualified' => ['www', 'example.com.', 'www.example.com.'];
        yield 'multi label relative name is qualified' => ['a.b', 'example.com.', 'a.b.example.com.'];
        yield 'absolute name ignores the origin' => ['www.other.test.', 'example.com.', 'www.other.test.'];
        yield 'origin without a dot still yields an absolute name' => ['www', 'example.com', 'www.example.com.'];
        yield 'empty name is the origin itself' => ['', 'example.com.', 'example.com.'];
        yield 'root origin leaves a single dot' => ['com', '.', 'com.'];
    }

    public function testIsAbsolute(): void
    {
        $this->assertTrue(DomainName::isAbsolute('example.com.'));
        $this->assertFalse(DomainName::isAbsolute('example.com'));
        $this->assertTrue(DomainName::isAbsolute('.'));
    }

    public function testEncodesLabelsAndTerminatesWithTheRootLabel(): void
    {
        $this->assertSame(
            "\x03www\x07example\x03com\x00",
            DomainName::fromString('www.example.com.')->encode()
        );
    }

    public function testEncodesARelativeNameExactlyLikeTheAbsoluteOne(): void
    {
        $this->assertSame(
            DomainName::fromString('www.example.com.')->encode(),
            DomainName::fromString('www.example.com')->encode()
        );
    }

    public function testEncodesTheRootAsASingleNullByte(): void
    {
        $this->assertSame("\x00", DomainName::fromString('.')->encode());
        $this->assertSame(1, DomainName::fromString('.')->getEncodedLength());
    }

    public function testEncodedLengthMatchesTheEncodedName(): void
    {
        $name = DomainName::fromString('www.example.com');

        $this->assertSame(strlen($name->encode()), $name->getEncodedLength());
    }

    public function testDecodesAWireNameAsAbsolute(): void
    {
        $this->assertSame(
            'www.example.com.',
            (string) DomainName::decode("\x03www\x07example\x03com\x00")
        );
    }

    public function testDecodesTheRootLabel(): void
    {
        $this->assertSame('.', (string) DomainName::decode("\x00"));
    }

    public function testDecodesANameStartingAtAnOffset(): void
    {
        $offset = 12;
        $this->assertSame('www.example.com.', (string) DomainName::decode(self::PACKET, $offset));
    }

    public function testDecodeStopsAtTheRootLabelAndIgnoresTrailingBytes(): void
    {
        $name = DomainName::decode("\x03www\x07example\x03com\x00\x00\x01\x00\x01");

        $this->assertSame('www.example.com.', (string) $name);
        $this->assertSame(17, $name->getEncodedLength());
    }

    #[DataProvider('compressedNameProvider')]
    public function testFollowsCompressionPointers(int $offset, string $expected): void
    {
        $this->assertSame($expected, (string) DomainName::decode(self::PACKET, $offset));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function compressedNameProvider(): iterable
    {
        yield 'pointer replacing a whole name' => [29, 'www.example.com.'];
        yield 'label followed by a pointer to a suffix' => [31, 'mail.example.com.'];
        yield 'pointer to another pointer' => [38, 'www.example.com.'];
        yield 'pointer to the root label' => [40, '.'];
    }

    /**
     * A compressed name occupies fewer bytes on the wire than the name it expands to, so
     * getEncodedLength() — derived from the labels — is not the number of bytes read and
     * cannot be used to walk to the next field. The two bytes at offset 29 expand to the
     * seventeen-byte name at offset 12.
     */
    public function testEncodedLengthIsTheExpandedLengthNotTheBytesRead(): void
    {
        $offset = 29;
        $this->assertSame(17, DomainName::decode(self::PACKET, $offset)->getEncodedLength());
    }

    #[DataProvider('malformedNameProvider')]
    public function testDecodeRejectsMalformedNames(string $buffer, int $offset = 0): void
    {
        $this->expectException(MalformedQueryException::class);

        DomainName::decode($buffer, $offset);
    }

    /**
     * @return iterable<string, array{string}|array{string, int}>
     */
    public static function malformedNameProvider(): iterable
    {
        yield 'truncated label' => ["\x03ww"];
        yield 'name without a root label' => ["\x03www"];
        yield 'length byte using reserved type 01' => ["\x40" . str_repeat('a', 64) . "\x00"];
        yield 'length byte using reserved type 10' => ["\x80\x00"];
        yield 'pointer missing its second byte' => ["\xc0"];
        yield 'pointer past the end of the buffer' => ["\xc0\x63"];
        yield 'pointer to itself' => ["\xc0\x00"];
        yield 'two pointers pointing at each other' => ["\xc0\x02\xc0\x00"];
        yield 'pointer to a later offset' => ["\xc0\x02\x03www\x00"];
        yield 'offset past the end of the buffer' => ["\x03www\x00", 99];
    }

    public function testEqualsIgnoresTheTrailingDotAndCase(): void
    {
        $name = DomainName::fromString('www.example.com.');

        $this->assertTrue($name->equals(DomainName::fromString('www.example.com')));
        $this->assertTrue($name->equals(DomainName::fromString('WWW.Example.COM.')));
        $this->assertFalse($name->equals(DomainName::fromString('www.example.org.')));
    }
}
