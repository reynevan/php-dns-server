<?php

namespace Reynevan\PhpDnsServer;

use Reynevan\PhpDnsServer\Cli\Options;
use Reynevan\PhpDnsServer\Cli\ServerOptions;
use Reynevan\PhpDnsServer\Transport\UdpSocket;
use Reynevan\PhpDnsServer\Zone\ZoneFileParser;

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
        $handler = new RequestHandler($zone);

        $port = $options->get('port');
        $address = $options->get('address');

        $socket = UdpSocket::bind($address, $port);
        echo "Listening on '$address:$port'\n";
        while (true) {
            $datagram = $socket->receive();
            if ($datagram === null) {
                continue;
            }
            $socket->send((string) $handler->handle($datagram->getBuffer()), $datagram->getPeer());
        }
    }
}
