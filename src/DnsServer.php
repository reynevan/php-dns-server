<?php

namespace Reynevan\PhpDnsServer;

use Reynevan\PhpDnsServer\Cli\Options;
use Reynevan\PhpDnsServer\Cli\ServerOptions;
use Reynevan\PhpDnsServer\Message\Exception\DnsException;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\Response;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Zone\ZoneFileParser;
use Throwable;

readonly class DnsServer
{
    public function run(Options $options): void
    {
        if ($options->get('help')) {
            ServerOptions::print();
            exit(0);
        }
        $parser = new ZoneFileParser();
        $zone = $options->get('zone');
        $zoneFile = str_starts_with($zone, '/') ? $zone : (__DIR__ . '/../' . $options->get('zone'));
        $content = file_get_contents($zoneFile);
        $zone = $parser->parse($content);

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            fwrite(STDERR, socket_strerror(socket_last_error()));
            exit(2);
        }

        $port = $options->get('port');
        $address = $options->get('address');
        if (!socket_bind($socket, $address, $port)) {
            fwrite(STDERR, socket_strerror(socket_last_error($socket)));
            exit(2);
        }

        echo "Listening on '$address:$port'\n";

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            $buffer = '';
            $clientIp = '';
            $clientPort = 0;

            $length = socket_recvfrom($socket, $buffer, 512, 0, $clientIp, $clientPort);

            if ($length === false || strlen($buffer) < 2) {
                continue;
            }
            $query = null;
            try {
                $query = new Query($buffer);
                $result = $zone->lookup($query);
                $response = Response::answer($query, $result->getRecords(), $result->getRCode());
            } catch (DnsException $e) {
                $response = Response::error($buffer, $e->getResponseCode(), $query ?? null);
            } catch (Throwable $e) {
                $response = Response::error($buffer, ResponseCode::SERVFAIL, $query ?? null);
            }
            socket_sendto($socket, $response, strlen($response), 0, $clientIp, $clientPort);
        }
    }
}
