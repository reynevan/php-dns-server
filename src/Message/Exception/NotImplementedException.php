<?php

namespace Reynevan\PhpDnsServer\Message\Exception;

use Reynevan\PhpDnsServer\Message\ResponseCode;

final class NotImplementedException extends DnsException
{
    public function getResponseCode(): ResponseCode
    {
        return ResponseCode::NOTIMP;
    }
}
