<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Message\Exception\NotImplementedException;
use Reynevan\PhpDnsServer\Record\Record;
use Reynevan\PhpDnsServer\Record\RecordPreamble;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Resolver\ResolutionResult;

readonly class Response
{
    public const int HEADER_LENGTH = 12;

    public const int MIN_PAYLOAD_SIZE = 512;

    public const int MAX_PAYLOAD_SIZE = 1232;

    private const int BADVERS_EXT_RCODE = 1;

    private function __construct(
        private string $txId,
        private ResponseFlags $flags,
        private ?Question $question = null,
        private RecordSet $records = new RecordSet([]),
        private RecordSet $authority = new RecordSet([]),
        private RecordSet $additional = new RecordSet([]),
        private ?Opt $opt = null
    ) {
    }

    public static function answer(Query $query, ResolutionResult $result, bool $recursionAvailable = true): self
    {
        $flags = new ResponseFlags(
            authoritative: $result->isAuthoritative(),
            recursionDesired: $query->getFlags()->isRecursionDesired(),
            recursionAvailable: $recursionAvailable,
            rCode: $result->getRCode()
        );

        return new self(
            $query->getTxId(),
            $flags,
            $query->getQuestion(),
            $result->getRecords(),
            $result->getAuthority(),
            $result->getAdditional(),
            self::respondingOpt($query)
        );
    }

    public static function error(string $buffer, ResponseCode $rCode, ?Query $query = null): self
    {
        $txId = $query?->getTxId() ?? str_pad(substr($buffer, 0, 2), 2, "\0");
        $flags = new ResponseFlags(rCode: $rCode);

        return new self(
            $txId,
            $flags,
            $query?->getQuestion(),
            new RecordSet(),
            opt: $query === null ? null : self::respondingOpt($query)
        );
    }

    public static function badVersion(Query $query): self
    {
        $flags = new ResponseFlags(
            recursionDesired: $query->getFlags()->isRecursionDesired(),
            recursionAvailable: true,
            rCode: ResponseCode::NOERROR
        );

        return new self(
            $query->getTxId(),
            $flags,
            $query->getQuestion(),
            new RecordSet(),
            opt: new Opt(payloadSize: self::MAX_PAYLOAD_SIZE, extRCode: self::BADVERS_EXT_RCODE)
        );
    }

    private static function respondingOpt(Query $query): ?Opt
    {
        return $query->getOpt() === null ? null : new Opt(payloadSize: self::MAX_PAYLOAD_SIZE);
    }

    public static function fromBuffer(string $buffer): self
    {
        $txId = substr($buffer, 0, 2);

        $flags = ResponseFlags::fromBuffer(substr($buffer, 2, 2));

        $qdCount = unpack('n', substr($buffer, 4, 2))[1];
        if ($qdCount > 1) {
            throw new NotImplementedException();
        }
        $anCount = unpack('n', substr($buffer, 6, 2))[1];
        $nsCount = unpack('n', substr($buffer, 8, 2))[1];
        $arCount = unpack('n', substr($buffer, 10, 2))[1];

        $offset = self::HEADER_LENGTH;

        $question = Question::fromBuffer(substr($buffer, $offset));

        $offset += $question->getName()->getEncodedLength() + 4;

        $records = self::decodeSection($buffer, $offset, $anCount);
        $authority = self::decodeSection($buffer, $offset, $nsCount);

        $additional = [];
        $opt = null;
        for ($i = 0; $i < $arCount; $i++) {
            $preamble = RecordPreamble::decode($buffer, $offset);
            if ($preamble->isOpt()) {
                $opt = Opt::fromPreamble($preamble, $buffer, $offset);
                continue;
            }
            $additional[] = Record::fromPreamble($preamble, $buffer, $offset);
        }

        return new self($txId, $flags, $question, $records, $authority, new RecordSet($additional), $opt);
    }


    private static function decodeSection(string $buffer, int &$offset, int $count): RecordSet
    {
        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $records[] = Record::fromBuffer($buffer, $offset);
        }

        return new RecordSet($records);
    }

    public function truncateTo(int $limit): self
    {
        if (strlen($this->encode()) <= $limit) {
            return $this;
        }

        $candidate = $this->withSections($this->records, $this->authority, new RecordSet());
        if (strlen($candidate->encode()) <= $limit) {
            return $candidate;
        }

        $candidate = $this->withSections($this->records, new RecordSet(), new RecordSet())->withTruncated();
        if (strlen($candidate->encode()) <= $limit) {
            return $candidate;
        }

        $records = $this->records->toArray();
        while ($records !== []) {
            array_pop($records);
            $candidate = $this
                ->withSections(new RecordSet($records), new RecordSet(), new RecordSet())
                ->withTruncated();
            if (strlen($candidate->encode()) <= $limit) {
                return $candidate;
            }
        }

        return $candidate;
    }

    private function withSections(RecordSet $records, RecordSet $authority, RecordSet $additional): self
    {
        return new self(
            $this->txId,
            $this->flags,
            $this->question,
            $records,
            $authority,
            $additional,
            $this->opt
        );
    }

    private function withTruncated(): self
    {
        return new self(
            $this->txId,
            $this->flags->withTruncated(),
            $this->question,
            $this->records,
            $this->authority,
            $this->additional,
            $this->opt
        );
    }

    public function encode(): string
    {
        $hasQuery = $this->question !== null;
        $additional = $this->additional->encode() . ($this->opt?->encode() ?? '');
        $arCount = $this->additional->count() + ($this->opt !== null ? 1 : 0);

        return implode('', [
            $this->txId,
            $this->flags->encode(),
            pack('n', $hasQuery ? 1 : 0),
            pack('n', $this->records->count()),
            pack('n', $this->authority->count()),
            pack('n', $arCount),
            $hasQuery ? $this->question->encode() : '',
            $this->records->encode(),
            $this->authority->encode(),
            $additional,
        ]);
    }

    public function getTxId(): string
    {
        return $this->txId;
    }

    public function getRecords(): RecordSet
    {
        return $this->records;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function getAuthority(): RecordSet
    {
        return $this->authority;
    }

    public function getAdditional(): RecordSet
    {
        return $this->additional;
    }

    public function getOpt(): ?Opt
    {
        return $this->opt;
    }

    public function getFlags(): ResponseFlags
    {
        return $this->flags;
    }
}
