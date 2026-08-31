<?php

namespace Unit;

use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Cli\InvalidOptionException;
use Reynevan\PhpDnsServer\Cli\Option;
use Reynevan\PhpDnsServer\Cli\Options;

class OptionsTest extends TestCase
{
    public function testParseShouldParseCorrectValues(): void
    {
        $options = Options::parse([
            'index.php',
            '-hp',
            '53',
            '--address',
            '127.0.0.1',
            '-v'
        ], $this->getOptionsDefinition());
        $this->assertSame($options->get('port'), 53);
        $this->assertSame($options->get('address'), '127.0.0.1');
        $this->assertSame($options->get('verbose'), true);
    }

    public function testParseShouldParseLongSyntaxWithEqualSign(): void
    {
        $options = Options::parse([
            'index.php',
            '--port=53',
        ], $this->getOptionsDefinition());
        $this->assertSame($options->get('port'), 53);
    }

    public function testParseShouldParseFlagAsNotLastOption(): void
    {
        $options = Options::parse([
            'index.php',
            '-v',
            '-p',
            '53'
        ], $this->getOptionsDefinition());
        $this->assertSame($options->get('verbose'), true);
        $this->assertSame($options->get('port'), 53);
    }

    public function testInvalidOptionShouldThrowException(): void
    {
        $this->expectException(InvalidOptionException::class);
        Options::parse([
            'index.php',
            '-p',
            '53',
            '--invalid-option',
            '127.0.0.1',
            '-v'
        ], $this->getOptionsDefinition());
    }

    /**
     * @return Option[]
     */
    private function getOptionsDefinition(): array
    {
        return[
            new Option('port', 'p', 5353, description: 'Port to listen to'),
            new Option('address', 'a', '0.0.0.0', description: 'Bind address'),
            new Option('zone', 'z', 'example.zone', description: 'Zone file'),
            new Option('verbose', 'v', false, description: 'Verbose mode', isFlag: true),
            new Option('help', 'h', false, description: 'Show this help', isFlag: true),
        ];
    }
}
