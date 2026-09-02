<?php

namespace Reynevan\PhpDnsServer;

use Reynevan\PhpDnsServer\Message\Exception\DnsException;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\Response;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Zone\Zone;
use Throwable;

readonly class RequestHandler
{
    public function __construct(private Zone $zone)
    {
     }

    public function handle(string $buffer): Response
    {
        try {
            $query = Query::fromBuffer($buffer);
            $result = $this->zone->lookup($query);
            $response = Response::answer($query, $result->getRecords(), $result->getRCode());
        } catch (DnsException $e) {
            $response = Response::error($buffer, $e->getResponseCode(), $query ?? null);
        } catch (Throwable $e) {
            $response = Response::error($buffer, ResponseCode::SERVFAIL, $query ?? null);
        }
        return $response;
    }
}