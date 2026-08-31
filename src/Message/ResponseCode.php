<?php

namespace Reynevan\PhpDnsServer\Message;

enum ResponseCode: int
{
    case NOERROR  = 0;
    case FORMERR  = 1;
    case SERVFAIL = 2;
    case NXDOMAIN  = 3;
    case NOTIMP   = 4;
    case REFUSED  = 5;
}
