<?php

namespace Reynevan\PhpDnsServer\Cache;

use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;
use Reynevan\PhpDnsServer\Record\SoaRecord;
use Reynevan\PhpDnsServer\Resolver\ResolutionResult;
use Reynevan\PhpDnsServer\Resolver\ResolutionType;

final readonly class TtlPolicy
{
    public function __construct(
        private int $maxTtl = 86400,
        private int $maxNegativeTtl = 10800,
        private int $failureTtl = 5,
    ) {
    }

    public function ttlFor(ResolutionResult $result): ?int
    {
        return match ($result->getResolutionType()) {
            ResolutionType::ANSWER   => $this->clamp($this->minTtl($result->getRecords()), $this->maxTtl),
            ResolutionType::REFERRAL => $this->clamp($this->minTtl($result->getAuthority()), $this->maxTtl),
            ResolutionType::NODATA,
            ResolutionType::NXDOMAIN => $this->negativeTtl($result->getAuthority()),
            ResolutionType::FAILURE  => $this->failureTtl,
        };
    }

    private function negativeTtl(RecordSet $authority): ?int
    {
        $soa = $authority->firstOfType(RecordType::SOA);
        if (!$soa instanceof SoaRecord) {
            return null;
        }

        return $this->clamp(min($soa->getTtl(), $soa->getMinimum()), $this->maxNegativeTtl);
    }

    private function minTtl(RecordSet $records): ?int
    {
        $ttls = array_map(fn(Record $record) => $record->getTtl(), $records->toArray());

        return $ttls === [] ? null : min($ttls);
    }

    private function clamp(?int $ttl, int $max): ?int
    {
        if ($ttl === null || $ttl <= 0) {
            return null;
        }

        return min($ttl, $max);
    }
}
