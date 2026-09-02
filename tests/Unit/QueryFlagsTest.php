<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\Opcode;
use Reynevan\PhpDnsServer\Message\QueryFlags;
use Reynevan\PhpDnsServer\Tests\Support\BufferBuilder;

class QueryFlagsTest extends TestCase
{


    #[DataProvider('encodedFlags')]
    public function testFromBufferParsesCorrectFlags($flags, $opcode, $recursion, $truncated)
    {
        $buffer = BufferBuilder::build(random_bytes(2), $flags);

        $flags = QueryFlags::fromBuffer($buffer);

        $this->assertEquals($opcode, $flags->getOpcode());
        $this->assertEquals($recursion, $flags->isRecursionDesired());
        $this->assertEquals($truncated, $flags->isTruncated());
    }

    public static function encodedFlags(): Generator
    {
        yield 'standard query, recursion desired' => [0x0100, Opcode::STANDARD, true, false];
        yield 'standard query, no recursion' => [0x0000, Opcode::STANDARD, false, false];
        yield 'standard query, no recursion, truncated' => [0x0200, Opcode::STANDARD, false, true];
        yield 'standard query, recursion desired, type response' => [0x8100, Opcode::STANDARD, true, false];
    }
}