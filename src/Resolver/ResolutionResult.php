<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Exception;
use Reynevan\PhpDnsServer\Message\DomainName;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Record\RecordType;

class ResolutionResult
{
    private function __construct(
        private readonly ResolutionType $resolutionType,
        private readonly bool $authoritative,
        private readonly RecordSet $records = new RecordSet(),
        private readonly RecordSet $authority = new RecordSet(),
        private readonly RecordSet $additional = new RecordSet(),
        private ResponseCode $rCode = ResponseCode::NOERROR
    ) {
    }

    public static function answer(RecordSet $records, bool $authoritative = false): self
    {
        return new self(ResolutionType::ANSWER, $authoritative, $records);
    }

    public static function referral(DomainName $zone, RecordSet $authority, RecordSet $additional): self
    {
        $nsRecords = $authority->ofType(RecordType::NS)->ofName($zone);
        if ($nsRecords->isEmpty()) {
            throw new Exception("No NS records for zone {$zone}");
        }

        $targets = array_map(fn(Record $ns) => new DomainName($ns->getRData()), $nsRecords->toArray());

        $glue = new RecordSet(array_values(array_filter(
            $additional->toArray(),
            fn(Record $r) => in_array($r->getType(), [RecordType::A, RecordType::AAAA], true)
                && array_any($targets, fn(DomainName $t) => $t->equals($r->getName())),
        )));

        return new self(ResolutionType::REFERRAL, authoritative: false, authority: $nsRecords, additional: $glue);
    }

    public static function noData(bool $authoritative = false, ?Record $soa = null): self
    {
        return new self(
            ResolutionType::NODATA,
            $authoritative,
            authority: $soa === null ? new RecordSet() : new RecordSet([$soa])
        );
    }

    public static function nxDomain(bool $authoritative = false, ?Record $soa = null): self
    {
        return new self(
            ResolutionType::NXDOMAIN,
            $authoritative,
            authority: $soa === null ? new RecordSet() : new RecordSet([$soa])
        );
    }

    public static function failure(ResponseCode $rCode): self
    {
        return new self(ResolutionType::FAILURE, authoritative: false, rCode: $rCode);
    }

    public function getResolutionType(): ResolutionType
    {
        return $this->resolutionType;
    }

    public function getRCode(): ResponseCode
    {
        return match ($this->resolutionType) {
            ResolutionType::ANSWER,
            ResolutionType::REFERRAL,
            ResolutionType::NODATA => ResponseCode::NOERROR,
            ResolutionType::NXDOMAIN => ResponseCode::NXDOMAIN,
            ResolutionType::FAILURE => $this->rCode
        };
    }

    public function getRecords(): RecordSet
    {
        return $this->records;
    }

    public function isAuthoritative(): bool
    {
        return $this->authoritative;
    }

    public function getAuthority(): RecordSet
    {
        return $this->authority;
    }

    public function getAdditional(): RecordSet
    {
        return $this->additional;
    }

    public function withTtl(int $ttl): self
    {
        return new self(
            $this->resolutionType,
            authoritative: false,
            records:    $this->records->withTtl($ttl),
            authority:  $this->authority->withTtl($ttl),
            additional: $this->additional->withTtl($ttl),
            rCode:      $this->rCode,
        );
    }
}
