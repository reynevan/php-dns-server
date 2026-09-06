<?php

namespace Reynevan\PhpDnsServer\Message;

use Reynevan\PhpDnsServer\Record\RecordPreamble;

readonly class Opt
{
    public const int TYPE = 41;

    /**
     * @param array<int, string> $options option code => option data
     */
    public function __construct(
        private int $payloadSize = 1232,
        private int $version = 0,
        private int $doBit = 0,
        private int $extRCode = 0,
        private array $options = []
    ) {
    }

    public static function fromPreamble(RecordPreamble $preamble, string $buffer, int &$offset): self
    {
        $ttl = $preamble->getTtl();
        $opt = new self(
            payloadSize: $preamble->getClass(),
            version: ($ttl >> 16) & 0xFF,
            doBit: ($ttl >> 15) & 0x01,
            extRCode: ($ttl >> 24) & 0xFF,
            options: self::decodeOptions(substr($buffer, $offset, $preamble->getRdLength()))
        );

        $offset += $preamble->getRdLength();

        return $opt;
    }

    public function encode(): string
    {
        $rData = $this->encodeOptions();

        return implode('', [
            DomainName::fromString(DomainName::ROOT)->encode(),
            pack('n', self::TYPE),
            pack('n', $this->payloadSize),
            pack('N', $this->encodeTtl()),
            pack('n', strlen($rData)),
            $rData
        ]);
    }

    public function getPayloadSize(): int
    {
        return $this->payloadSize;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function isDnssecOk(): bool
    {
        return $this->doBit === 1;
    }

    public function getExtRCode(): int
    {
        return $this->extRCode;
    }

    /**
     * @return array<int, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    private function encodeTtl(): int
    {
        return ($this->extRCode << 24) | ($this->version << 16) | ($this->doBit << 15);
    }

    private function encodeOptions(): string
    {
        $result = '';
        foreach ($this->options as $code => $data) {
            $result .= pack('nn', $code, strlen($data)) . $data;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private static function decodeOptions(string $rData): array
    {
        $options = [];
        $offset = 0;

        while ($offset + 4 <= strlen($rData)) {
            ['code' => $code, 'length' => $length] = unpack('ncode/nlength', substr($rData, $offset, 4));
            $offset += 4;

            if ($offset + $length > strlen($rData)) {
                break;
            }

            $options[$code] = substr($rData, $offset, $length);
            $offset += $length;
        }

        return $options;
    }
}
