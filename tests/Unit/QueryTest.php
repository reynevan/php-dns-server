<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\Exception\NotImplementedException;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Tests\Support\BufferBuilder;

class QueryTest extends TestCase
{

    public function testQueryFromValidBuffer()
    {
        $txId = random_bytes(2);
        $name = 'example.com.';
        $buffer = BufferBuilder::build($txId, 0x0100, $name, type: RecordType::A->toInt());

        $query = Query::fromBuffer($buffer);

        $this->assertEquals($txId, $query->getTxId());
        $this->assertEquals('example.com.', $query->getQuestion()->getName());
        $this->assertEquals(RecordType::A, $query->getQuestion()->getType());
    }

    public function testQueryFromBufferWithInvalidOpcode()
    {
        $buffer = BufferBuilder::build(random_bytes(2), 0x1100);

        $this->expectException(NotImplementedException::class);
        Query::fromBuffer($buffer);
    }

    public function testQueryFromBufferWithInvalidRecordType()
    {
        $buffer = BufferBuilder::build(random_bytes(2), 0x0100, type: 18);

        $this->expectException(NotImplementedException::class);
        Query::fromBuffer($buffer);
    }
}