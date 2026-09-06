<?php

namespace Reynevan\PhpDnsServer;

use Reynevan\PhpDnsServer\Cache\InMemoryCache;
use Reynevan\PhpDnsServer\Cli\Options;
use Reynevan\PhpDnsServer\Cli\ServerOptions;
use Reynevan\PhpDnsServer\Resolver\AuthoritativeResolver;
use Reynevan\PhpDnsServer\Resolver\CachedResolver;
use Reynevan\PhpDnsServer\Resolver\RecursiveResolver;
use Reynevan\PhpDnsServer\Resolver\ResolverChain;
use Reynevan\PhpDnsServer\Resolver\RootHints;
use Reynevan\PhpDnsServer\Resolver\ServerIdentityResolver;
use Reynevan\PhpDnsServer\Transport\UdpSocket;
use Reynevan\PhpDnsServer\Transport\UdpTransport;
use Reynevan\PhpDnsServer\Zone\ZoneFileParser;

readonly class DnsServer
{
    public function run(Options $options): void
    {
        if ($options->get('help')) {
            ServerOptions::print();
            exit(0);
        }

        $rootHints = RootHints::fromFile(__DIR__ . '/../named.root');

        $zoneParser = new ZoneFileParser();
        $zone = $options->get('zone');
        $zoneFile = str_starts_with($zone, '/') ? $zone : (__DIR__ . '/../' . $options->get('zone'));
        $content = file_get_contents($zoneFile);
        $zone = $zoneParser->parse($content);

        $cache = new InMemoryCache();

        $resolver = new ResolverChain([
            new ServerIdentityResolver($options),
            new AuthoritativeResolver($zone),
            new CachedResolver(new RecursiveResolver($rootHints, new UdpTransport(), $cache, $options), $cache)
        ]);

        $handler = new RequestHandler($resolver, $options);

        $port = $options->get('port');
        $address = $options->get('address');

        $socket = UdpSocket::bind($address, $port);
        echo "Listening on '$address:$port'\n";
        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            $datagram = $socket->receive();
            if ($datagram === null) {
                continue;
            }
            $response = $handler->handle($datagram->getBuffer());
            $socket->send($response->encode(), $datagram->getPeer());
        }
    }
}
