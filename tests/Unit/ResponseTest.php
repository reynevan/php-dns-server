<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Response;
use Reynevan\PhpDnsServer\Record\RecordClass;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Tests\Support\ResponseBufferBuilder;

class ResponseTest extends TestCase
{
    public function testResponseFromBuffer()
    {
        $txId = "\x12\x34";
        $bufferBuilder = ResponseBufferBuilder::create($txId);
        $bufferBuilder
            ->withQuestion()
            ->withAuthority('com', RecordType::NS, 'a.gtld-servers.net.')
            ->withAuthority('com', RecordType::NS, 'b.gtld-servers.net.')
            ->withAdditional('a.gtld-servers.net.', RecordType::A, '192.5.6.30')
            ->withAdditional('b.gtld-servers.net.', RecordType::A, '192.33.14.30')
            ->withAdditional('c.gtld-servers.net.', RecordType::A, '192.26.92.30')
            ->withAnswer('google.com', RecordType::A, '142.250.109.102')
            ->withAnswer('google.com', RecordType::A, '142.250.109.113')
        ;
        $buffer = $bufferBuilder->build();

        $response = Response::fromBuffer($buffer);

        $this->assertEquals($txId, $response->getTxId());
        $this->assertEquals('example.com.', (string) $response->getQuestion()?->getName());
        $this->assertEquals(RecordType::A, $response->getQuestion()?->getType());
        $this->assertEquals(RecordClass::IN, $response->getQuestion()?->getClass());

        $this->assertCount(2, $response->getAuthority()->toArray());
        $this->assertEquals(3600, $response->getAuthority()->toArray()[0]->getTtl());
        $this->assertEquals(DomainName::fromString('com'), $response->getAuthority()->toArray()[0]->getName());
        $this->assertEquals(
            DomainName::fromString('a.gtld-servers.net'),
            DomainName::fromString($response->getAuthority()->toArray()[0]->getRData())
        );

        $this->assertCount(3, $response->getAdditional()->toArray());
        $this->assertEquals(3600, $response->getAdditional()->toArray()[0]->getTtl());
        $this->assertEquals(
            DomainName::fromString('a.gtld-servers.net'),
            $response->getAdditional()->toArray()[0]->getName()
        );
        $this->assertEquals('192.5.6.30', $response->getAdditional()->toArray()[0]->getRData());

        $this->assertCount(2, $response->getRecords()->toArray());
        $this->assertEquals(3600, $response->getRecords()->toArray()[0]->getTtl());
        $this->assertEquals(DomainName::fromString('google.com'), $response->getRecords()->toArray()[0]->getName());
        $this->assertEquals('142.250.109.102', $response->getRecords()->toArray()[0]->getRData());
    }

    public function testResponseFromBufferReadsOptWithoutLosingTheRestOfAdditional()
    {
        $buffer = ResponseBufferBuilder::create()
            ->withQuestion('www.example.com.', RecordType::A)
            ->withAdditional('a.gtld-servers.net.', RecordType::A, '192.5.6.30')
            ->withOpt(payloadSize: 4096, dnssecOk: true)
            ->withAdditional('b.gtld-servers.net.', RecordType::A, '192.33.14.30')
            ->build();

        $response = Response::fromBuffer($buffer);

        $this->assertNotNull($response->getOpt());
        $this->assertEquals(4096, $response->getOpt()->getPayloadSize());
        $this->assertEquals(0, $response->getOpt()->getVersion());
        $this->assertTrue($response->getOpt()->isDnssecOk());

        // The OPT is not zone data, so it never joins the section - but the records after it must survive.
        $this->assertCount(2, $response->getAdditional()->toArray());
        $this->assertEquals(
            DomainName::fromString('b.gtld-servers.net'),
            $response->getAdditional()->toArray()[1]->getName()
        );
        $this->assertEquals('192.33.14.30', $response->getAdditional()->toArray()[1]->getRData());
    }
}
