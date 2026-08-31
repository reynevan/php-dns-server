<?php

namespace Reynevan\PhpDnsServer\Message\Exception;

use Reynevan\PhpDnsServer\Message\ResponseCode;

abstract class DnsException extends \RuntimeException
{
    abstract public function getResponseCode(): ResponseCode;
}
