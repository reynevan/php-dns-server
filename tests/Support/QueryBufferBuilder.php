<?php

namespace Reynevan\PhpDnsServer\Tests\Support;

use Reynevan\PhpDnsServer\Message\DomainName;

class QueryBufferBuilder
{
    public static function build(
        string $txId,
        string $flags,
        string $domain = 'example.com.',
        int $qdCount = 1,
        int $anCount = 0,
        int $nsCount = 0,
        int $arCount = 0,
        int $type = 1,
        int $rClass = 1
    ): string {
        return implode('', [
            $txId,
            pack('n', $flags),
            pack('n', $qdCount),
            pack('n', $anCount),
            pack('n', $nsCount),
            pack('n', $arCount),
            DomainName::fromString($domain)->encode(),
            pack('n', $type),
            pack('n', $rClass),
        ]);
    }
}
