<?php

namespace Reynevan\PhpDnsServer\Cli;

class InvalidOptionException extends \Exception
{
    public function __construct(string $option)
    {
        parent::__construct(sprintf(
            'The "%s" option is invalid.',
            $option
        ));
    }
}
