<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\Opcode;
use Reynevan\PhpDnsServer\Message\QueryFlags;
use Reynevan\PhpDnsServer\Tests\Support\QueryBufferBuilder;

class QueryFlagsTest extends TestCase
{
    #[DataProvider('encodedFlags')]
    public function testFromBufferParsesCorrectFlags($flags, $opcode, $recursion, $truncated)
    {
        $buffer = QueryBufferBuilder::build(random_bytes(2), $flags);

        $flags = QueryFlags::fromBuffer(substr($buffer, 2, 2));

        $this->assertEquals($opcode, $flags->getOpcode());
        $this->assertEquals($recursion, $flags->isRecursionDesired());
        $this->assertEquals($truncated, $flags->isTruncated());
    }

    public static function encodedFlags(): Generator
    {
        yield 'recursion desired' => [0x0100, Opcode::STANDARD, true, false];
        yield 'no recursion' => [0x0000, Opcode::STANDARD, false, false];
        yield 'no recursion, truncated' => [0x0200, Opcode::STANDARD, false, true];
        yield 'recursion desired, type response' => [0x8100, Opcode::STANDARD, true, false];
    }
}
