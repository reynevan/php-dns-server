<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;
use Reynevan\PhpDnsServer\Message\Exception\NotImplementedException;
use Reynevan\PhpDnsServer\Record\RecordPreamble;
use Reynevan\PhpDnsServer\Record\RecordType;

class Query
{
    private const int HEADER_LENGTH = 12;

    private function __construct(
        private string $txId,
        private Question $question,
        private QueryFlags $flags,
        private ?Opt $opt = null
    ) {
    }

    public function getTxId(): string
    {
        return $this->txId;
    }

    public function getFlags(): QueryFlags
    {
        return $this->flags;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function getOpt(): ?Opt
    {
        return $this->opt;
    }

    public function withOpt(Opt $opt): self
    {
        $query = clone $this;
        $query->opt = $opt;
        return $query;
    }

    /**
     * @throws MalformedQueryException
     * @throws NotImplementedException
     */
    public static function fromBuffer(string $buffer): self
    {
        if (strlen($buffer) < self::HEADER_LENGTH) {
            throw new MalformedQueryException();
        }

        $txId = substr($buffer, 0, 2);
        $flags = QueryFlags::fromBuffer(substr($buffer, 2, 2));
        if ($flags->getOpcode() !== Opcode::STANDARD) {
            throw new NotImplementedException();
        }

        $counts = unpack('nqdCount/nanCount/nnsCount/narCount', substr($buffer, 4, 8));
        if ($counts === false) {
            throw new MalformedQueryException();
        }
        if ($counts['qdCount'] !== 1) {
            throw new MalformedQueryException();
        }

        $offset = self::HEADER_LENGTH;
        $name = DomainName::decode($buffer, $offset);
        $type = self::parseType(substr($buffer, $offset, 2));
        $offset += 4;

        $question = new Question($name, $type);
        $opt = self::findOpt($buffer, $offset, $counts['anCount'] + $counts['nsCount'], $counts['arCount']);

        return new self($txId, $question, $flags, $opt);
    }

    public static function for(Question $question, bool $recursionDesired = false): self
    {
        $flags = new QueryFlags(recursionDesired:  $recursionDesired);

        return new self(random_bytes(2), $question, $flags);
    }

    /**
     * @throws MalformedQueryException
     */
    private static function findOpt(string $buffer, int $offset, int $skipCount, int $arCount): ?Opt
    {
        for ($i = 0; $i < $skipCount; $i++) {
            $offset += RecordPreamble::decode($buffer, $offset)->getRdLength();
        }

        $opt = null;
        for ($i = 0; $i < $arCount; $i++) {
            $preamble = RecordPreamble::decode($buffer, $offset);
            if (!$preamble->isOpt()) {
                $offset += $preamble->getRdLength();
                continue;
            }
            if ($opt !== null) {
                throw new MalformedQueryException();
            }
            $opt = Opt::fromPreamble($preamble, $buffer, $offset);
        }

        return $opt;
    }

    /**
     * @throws MalformedQueryException
     * @throws NotImplementedException
     */
    private static function parseType(string $buffer): RecordType
    {
        $type = unpack('n', $buffer);
        if (!$type || !isset($type[1])) {
            throw new MalformedQueryException();
        }
        $type = RecordType::fromInt($type[1]);
        if ($type === null) {
            throw new NotImplementedException();
        }

        return $type;
    }

    public function encode(): string
    {
        return implode('', [
            $this->txId,
            $this->flags->encode(),
            pack('n', 1),
            pack('n', 0),
            pack('n', 0),
            pack('n', $this->opt !== null ? 1 : 0),
            $this->question->encode(),
            $this->opt?->encode() ?? ''
        ]);
    }
}
