<?php

namespace Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;

class DomainNameTest extends TestCase
{
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

    public function testDecodeIgnoresTrailingBytesButReportsTheirOffset(): void
    {
        $name = DomainName::decode("\x03www\x07example\x03com\x00\x00\x01\x00\x01");

        $this->assertSame('www.example.com.', (string) $name);
        $this->assertSame(17, $name->getEncodedLength());
    }

    public function testDecodeRejectsATruncatedName(): void
    {
        $this->expectException(MalformedQueryException::class);

        DomainName::decode("\x03ww");
    }

    public function testDecodeRejectsANameWithoutARootLabel(): void
    {
        $this->expectException(MalformedQueryException::class);

        DomainName::decode("\x03www");
    }

    public function testEqualsIgnoresTheTrailingDotAndCase(): void
    {
        $name = DomainName::fromString('www.example.com.');

        $this->assertTrue($name->equals(DomainName::fromString('www.example.com')));
        $this->assertTrue($name->equals(DomainName::fromString('WWW.Example.COM.')));
        $this->assertFalse($name->equals(DomainName::fromString('www.example.org.')));
    }
}
