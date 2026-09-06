<?php

namespace Reynevan\PhpDnsServer;

use Reynevan\PhpDnsServer\Cli\Options;
use Reynevan\PhpDnsServer\Message\Exception\DnsException;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\Response;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Resolver\Resolver;
use Throwable;

readonly class RequestHandler
{
    public function __construct(private Resolver $resolver, private Options $options)
    {
    }

    public function handle(string $buffer): Response
    {
        $query = null;
        try {
            $query = Query::fromBuffer($buffer);
            $response = self::badVersion($query) ?? Response::answer($query, $this->resolver->resolve($query), $this->options->get('recursive'));
        } catch (DnsException $e) {
            $response = Response::error($buffer, $e->getResponseCode(), $query);
        } catch (Throwable) {
            $response = Response::error($buffer, ResponseCode::SERVFAIL, $query);
        }

        return $response->truncateTo(self::maxSize($query));
    }

    private static function badVersion(Query $query): ?Response
    {
        $opt = $query->getOpt();
        if ($opt === null || $opt->getVersion() === 0) {
            return null;
        }

        return Response::badVersion($query);
    }

    private static function maxSize(?Query $query): int
    {
        $opt = $query?->getOpt();
        if ($opt === null) {
            return Response::MIN_PAYLOAD_SIZE;
        }

        return max(Response::MIN_PAYLOAD_SIZE, min($opt->getPayloadSize(), Response::MAX_PAYLOAD_SIZE));
    }
}
