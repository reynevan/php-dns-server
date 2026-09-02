<?php

namespace Reynevan\PhpDnsServer\Message;

enum OperationType: int
{
    case QUERY = 0;
    case RESPONSE = 1;
}
