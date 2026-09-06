<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Cli\Options;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Record\PtrRecord;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;

readonly class ServerIdentityResolver implements Resolver
{
    public function __construct(private Options $options)
    {
    }

    public function supports(Query $query): bool
    {
        return $query->getQuestion()->getName()->equals($this->getServerIdentityName());
    }

    public function resolve(Query $query): ResolutionResult
    {
        $record = Record::create(RecordType::PTR);
        $record
            ->setName($this->getServerIdentityName())
            ->setTtl(0)
            ->setRData($this->options->get('identity-name'));
        return ResolutionResult::answer(new RecordSet([$record]), authoritative: true);
    }

    private function getServerIdentityName(): DomainName
    {
        $reversedIp = implode('.', array_reverse(explode('.', $this->options->get('identity-address'))));
        return DomainName::fromString($reversedIp . '.in-addr.arpa');
    }
}
