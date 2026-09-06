<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Cache\Cache;
use Reynevan\PhpDnsServer\Cache\CacheKey;
use Reynevan\PhpDnsServer\Cli\Options;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\Question;
use Reynevan\PhpDnsServer\Message\Response;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Transport\Transport;
use Throwable;

readonly class RecursiveResolver implements Resolver
{
    public function __construct(
        private RootHints $rootHints,
        private Transport $transport,
        private Cache $cache,
        private Options $options,
    ) {
    }

    public function supports(Query $query): bool
    {
        return $this->options->get('recursive') && $query->getFlags()->isRecursionDesired();
    }

    public function resolve(Query $query): ResolutionResult
    {
        return $this->resolveWithContext($query, new ResolutionContext());
    }

    private function resolveWithContext(Query $query, ResolutionContext $context): ResolutionResult
    {
        $cnameRecords = [];
        do {
            if (!$context->canFollowCName()) {
                return ResolutionResult::failure(ResponseCode::SERVFAIL);
            }
            $context->followCName();
            $result = $this->iterate($query, $context);

            if ($result->getRCode() === ResponseCode::NOERROR) {
                if ($result->getRecords()->hasType($query->getQuestion()->getType())) {
                    if (count($cnameRecords) > 0) {
                        $mergedRecords = $result->getRecords()->merge(new RecordSet($cnameRecords));
                        $result = ResolutionResult::answer($mergedRecords);
                    }
                    return $result;
                } elseif (
                    $query->getQuestion()->getType() !== RecordType::CNAME &&
                    $result->getRecords()->hasType(RecordType::CNAME)
                ) {
                    $cnameRecord = $result->getRecords()->lastOfType(RecordType::CNAME);
                    $cnameRecords[] = $cnameRecord;
                    $name = DomainName::fromString($cnameRecord->getRData());
                    $question = new Question($name, $query->getQuestion()->getType());
                    $query = Query::for($question);
                } else {
                    return $result;
                }
            } else {
                return $result;
            }
        } while ($context->canFollowCName());

        return $result;
    }

    private function iterate(Query $query, ResolutionContext $context): ResolutionResult
    {
        $delegation = $this->closestDelegation($query->getQuestion()->getName());

        $zone = $delegation->zone ?? DomainName::fromString('.');
        $nameServers = $delegation->nameServers ?? $this->rootHints->getNameServers();

        while ($context->hasQueryBudget()) {
            $ns = $nameServers->next();

            if ($ns === null) {
                return ResolutionResult::failure(ResponseCode::SERVFAIL);
            }

            $address = $ns->getAddressA()[0] ?? null;
            if ($address === null) {
                if (!$context->enterNsLookup($query->getQuestion())) {
                    $nameServers->markDead($ns);
                    continue;
                }
                try {
                    $subQuery = Query::for(new Question($ns->getName(), RecordType::A));
                    $subResponse = $this->resolveWithContext($subQuery, $context);
                } finally {
                    $context->leaveNsLookup($query->getQuestion());
                }
                if ($subResponse->getRecords()->isEmpty()) {
                    $nameServers->markDead($ns);
                    continue;
                }
                $ns = $ns->withAddresses($subResponse->getRecords());
                $address = $ns->getAddressA()[0] ?? null;
                if ($address === null) {
                    $nameServers->markDead($ns);
                    continue;
                }
            }
            if (!$context->visitServer($address, $query->getQuestion())) {
                $nameServers->markDead($ns);
                continue;
            }
            $context->spendQuery();
            try {
                $response = $this->transport->ask($address, $query);
                if (!$response) {
                    $nameServers->markDead($ns);
                    continue;
                }
            } catch (Throwable $e) {
                $nameServers->markDead($ns);
                continue;
            }

            if ($response->getTxId() !== $query->getTxId()) {
                $nameServers->markDead($ns);
                continue;
            }

            if ($response->getFlags()->getRCode() === ResponseCode::NXDOMAIN) {
                return ResolutionResult::nxDomain(soa: $response->getAuthority()->firstOfType(RecordType::SOA));
            }

            if ($response->getFlags()->getRCode() === ResponseCode::SERVFAIL) {
                $nameServers->markDead($ns);
                continue;
            }

            if (!$response->getRecords()->isEmpty()) {
                return ResolutionResult::answer($response->getRecords());
            }

            if ($response->getAuthority()->hasType(RecordType::NS)) {
                $newZone = $response->getAuthority()->firstOfType(RecordType::NS)->getName();
                if (!$this->isValidReferral($response, $newZone, $zone, $query)) {
                    return ResolutionResult::failure(ResponseCode::SERVFAIL);
                }
                $zone = $newZone;
                $this->cache->set(
                    new CacheKey($newZone, RecordType::NS),
                    ResolutionResult::referral($zone, $response->getAuthority(), $response->getAdditional()),
                );
                $nameServers = NameServerSet::fromRecords($response->getAuthority(), $response->getAdditional());
                continue;
            }

            if (
                $response->getFlags()->getRCode() === ResponseCode::NOERROR &&
                $response->getAuthority()->hasType(RecordType::SOA)
            ) {
                return ResolutionResult::noData(soa: $response->getAuthority()->firstOfType(RecordType::SOA));
            }
        }
        return ResolutionResult::failure(ResponseCode::SERVFAIL);
    }

    private function isValidReferral(
        Response $response,
        DomainName $newZone,
        DomainName $currentZone,
        Query $query
    ): bool {
        if (
            array_any(
                $response->getAuthority()->ofType(RecordType::NS)->toArray(),
                fn($record) => !$record->getName()->equals($newZone)
            )
        ) {
            return false;
        }
        if (!$query->getQuestion()->getName()->isWithin($newZone) || $newZone->equals($currentZone)) {
            return false;
        }
        return true;
    }

    private function closestDelegation(DomainName $name): ?Delegation
    {
        foreach ($name->parents() as $candidate) {
            if ($candidate->equals(DomainName::fromString('.'))) {
                return null;
            }

            $hit = $this->cache->get(new CacheKey($candidate, RecordType::NS));
            if ($hit === null || !$hit->getAuthority()->hasType(RecordType::NS)) {
                continue;
            }

            return new Delegation(
                $candidate,
                NameServerSet::fromRecords($hit->getAuthority(), $hit->getAdditional()),
            );
        }

        return null;
    }
}
