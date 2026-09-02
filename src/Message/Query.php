<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Message\Exception\MalformedQueryException;
use Reynevan\PhpDnsServer\Message\Exception\NotImplementedException;
use Reynevan\PhpDnsServer\Record\RecordType;

class Query
{
    private const int HEADER_LENGTH = 12;


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

    private function __construct(private string $txId, private Question $question, private QueryFlags $flags)
    {
    }

    public static function fromBuffer(string $buffer): self
    {
        $txId = substr($buffer, 0, 2);
        $flags = QueryFlags::fromBuffer($buffer);
        if ($flags->getOpcode() !== Opcode::STANDARD) {
            throw new NotImplementedException();
        }
        $name = DomainName::decode(substr($buffer, self::HEADER_LENGTH));
        $type = self::parseType(substr($buffer, self::HEADER_LENGTH + $name->getEncodedLength(), 2));
        $question = new Question($name, $type);
        return new self($txId, $question, $flags);
    }

    public static function for(Question $question): self
    {
        $flags = new QueryFlags(recursionDesired:  true);

        return new self(random_bytes(2), $question, $flags);
    }

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
        $questionCount = 1;
        return implode('', [
            $this->txId,
            $this->flags->encode(),
            pack('n', $questionCount),
            pack('n', 0),
            pack('n', 0),
            pack('n', 0),
            $this->question->encode()
        ]);
    }
}
