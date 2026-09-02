<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Record\Record;

readonly class Response
{
    /**
     * @param string $txId
     * @param Query|null $query
     * @param Record[] $records
     * @param ResponseCode $rCode
     */
    private function __construct(
        private string $txId,
        private ?Query $query = null,
        private array $records = [],
        private ResponseCode $rCode = ResponseCode::NOERROR,
    ) {
    }

    /**
     * @param Query $query
     * @param Record[] $records
     * @param ResponseCode $rCode
     * @return self
     */
    public static function answer(Query $query, array $records, ResponseCode $rCode): self
    {
        return new self($query->getTxId(), $query, $records, $rCode);
    }

    public static function error(string $buffer, ResponseCode $rCode, ?Query $query = null): self
    {
        $txId = $query?->getTxId() ?? str_pad(substr($buffer, 0, 2), 2, "\0");

        return new self($txId, $query, [], $rCode);
    }

    public function __toString(): string
    {
        $hasQuery = $this->query !== null;

        return implode('', [
            $this->txId,
            $this->formatFlags(),
            pack('n', $hasQuery ? 1 : 0),
            pack('n', count($this->records)),
            pack('n', 0),
            pack('n', 0),
            $hasQuery ? $this->query->getQuestion()?->encode() : '',
            $this->formatAnswers()
        ]);
    }

    private function formatFlags(): string
    {
        $authoritative = $this->rCode === ResponseCode::NOERROR
            || $this->rCode === ResponseCode::NXDOMAIN;

        $qr = 1;
        $opcode = $this->query?->getFlags()->getOpcode()?->value ?? 0;
        $aa = $authoritative ? 1 : 0;
        $tc = 0;
        $rd = $this->query?->getFlags()->isRecursionDesired() ?? 0;
        $ra = 0;
        $z = 0;

        $flags = ($qr << 15)
            | ($opcode << 11)
            | ($aa << 10)
            | ($tc << 9)
            | ($rd << 8)
            | ($ra << 7)
            | ($z << 4)
            | $this->rCode->value;

        return pack('n', $flags);
    }

    private function formatAnswers(): string
    {
        return implode('', array_map(strval(...), $this->records));
    }
}
