<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Question;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Resolver\ResolutionContext;

class ResolutionContextTest extends TestCase
{
    public function testCannotReenterNsLookup(): void
    {
        $context = new ResolutionContext();
        $question = new Question(DomainName::fromString('example.com'), RecordType::A);

        $this->assertTrue($context->enterNsLookup($question));
        $this->assertFalse($context->enterNsLookup($question));
    }

    public function testCanLookupSameNsAfterLeaving(): void
    {
        $context = new ResolutionContext();
        $question = new Question(DomainName::fromString('example.com'), RecordType::A);

        $this->assertTrue($context->enterNsLookup($question));
        $context->leaveNsLookup($question);
        $this->assertTrue($context->enterNsLookup($question));
    }

    public function testCannotEnterNsLookupOverLimit(): void
    {
        $context = new ResolutionContext(maxNsLookupDepth: 1);
        $questionA = new Question(DomainName::fromString('a.example.com'), RecordType::NS);
        $questionB = new Question(DomainName::fromString('b.example.com'), RecordType::NS);
        $this->assertTrue($context->enterNsLookup($questionA));
        $this->assertFalse($context->enterNsLookup($questionB));
    }

    public function testCannotFollowCNameOverLimit(): void
    {
        $context = new ResolutionContext(maxCnameHops: 2);
        $this->assertTrue($context->canFollowCName());
        $context->followCName();
        $this->assertTrue($context->canFollowCName());
        $context->followCName();
        $this->assertFalse($context->canFollowCName());
    }

    public function testCannotRevisitSameServer(): void
    {
        $context = new ResolutionContext();
        $address = '192.168.1.1';
        $question = new Question(DomainName::fromString('example.com'), RecordType::A);
        $this->assertTrue($context->visitServer($address, $question));
        $this->assertFalse($context->visitServer($address, $question));
    }

    public function testCanRevisitServerWithDifferentNameQuestion(): void
    {
        $context = new ResolutionContext();
        $address = '192.168.1.1';
        $questionA = new Question(DomainName::fromString('a.example.com'), RecordType::A);
        $questionB = new Question(DomainName::fromString('b.example.com'), RecordType::A);
        $this->assertTrue($context->visitServer($address, $questionA));
        $this->assertTrue($context->visitServer($address, $questionB));
    }

    public function testCanRevisitServerWithDifferentTypeQuestion(): void
    {
        $context = new ResolutionContext();
        $address = '192.168.1.1';
        $questionA = new Question(DomainName::fromString('example.com'), RecordType::A);
        $questionB = new Question(DomainName::fromString('example.com'), RecordType::TXT);
        $this->assertTrue($context->visitServer($address, $questionA));
        $this->assertTrue($context->visitServer($address, $questionB));
    }

    public function testQueryBudgetIsAvailableUntilExhausted(): void
    {
        $context = new ResolutionContext(maxQueries: 2);
        $this->assertTrue($context->hasQueryBudget());
        $context->spendQuery();
        $this->assertTrue($context->hasQueryBudget());
        $context->spendQuery();
        $this->assertFalse($context->hasQueryBudget());
    }
}
