<?php

namespace Reynevan\PhpDnsServer\Message\Exception;

use Reynevan\PhpDnsServer\Message\ResponseCode;

final class MalformedQueryException extends DnsException
{
    public function getResponseCode(): ResponseCode
    {
        return ResponseCode::FORMERR;
    }
}
