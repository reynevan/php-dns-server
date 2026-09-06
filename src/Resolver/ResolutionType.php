<?php

namespace Reynevan\PhpDnsServer\Resolver;

enum ResolutionType
{
    case ANSWER;
    case REFERRAL;
    case NODATA;
    case NXDOMAIN;
    case FAILURE;
}
