<?php

namespace Reynevan\PhpDnsServer\Transport;

class SocketException extends \Exception
{
    public function __construct(string $message = "")
    {
        parent::__construct($message);
    }
}
