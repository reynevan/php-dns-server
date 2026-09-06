<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\Opcode;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Message\ResponseFlags;

class ResponseFlagsTest extends TestCase
{
    #[DataProvider('encodedFlags')]
    public function testFromBufferParsesCorrectFlags(
        $buffer,
        $opcode,
        $authoritative,
        $truncated,
        $recursionDesired,
        $recursionAvailable,
        $answerAuthenticated,
        $nonAuthDataAcceptable,
        ResponseCode $responseCode
    ) {
        $flags = ResponseFlags::fromBuffer($buffer);

        $this->assertEquals($opcode, $flags->getOpcode());
        $this->assertEquals($authoritative, $flags->isAuthoritative());
        $this->assertEquals($truncated, $flags->isTruncated());
        $this->assertEquals($recursionDesired, $flags->isRecursionDesired());
        $this->assertEquals($recursionAvailable, $flags->isRecursionAvailable());
        $this->assertEquals($answerAuthenticated, $flags->isAnswerAuthenticated());
        $this->assertEquals($nonAuthDataAcceptable, $flags->isNonAuthDataAcceptable());
        $this->assertEquals($responseCode, $flags->getRCode());
    }

    public static function encodedFlags(): Generator
    {
        yield [pack('n', 0x8000), Opcode::STANDARD, false, false, false, false, false, false, ResponseCode::NOERROR];
        yield [pack('n', 0x87b0), Opcode::STANDARD, true, true, true, true, true, true, ResponseCode::NOERROR];
        yield [pack('n', 0x8523), Opcode::STANDARD, true, false, true, false, true, false, ResponseCode::NXDOMAIN];
    }
}
