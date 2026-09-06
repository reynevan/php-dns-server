<?php

namespace Reynevan\PhpDnsServer\Cli;

class ServerOptions
{
    /**
     * @return Option[]
     */
    public static function all(): array
    {
        return [
            new Option('port', 'p', 5353, description: '-p/--port <port>: Port to listen on'),
            new Option('address', 'a', '0.0.0.0', description: '-a/--address <address>: Bind address'),
            new Option('zone', 'z', 'example.zone', description: '-z/--zone <file>: Zone file'),
            new Option(
                'identity-address',
                'i',
                '127.0.0.1',
                description:  '-i/--identity-address <address>: Server identity address'
            ),
            new Option(
                'identity-name',
                'n',
                'dns.rnvn',
                description:  '-n/--identity-name <name>: Server identity name'
            ),
            new Option('recursive', 'r', false, description: '-r/--recursive: Recursion available', isFlag: true),
            new Option('help', 'h', false, description: '-h/--help: Show this help', isFlag: true),
        ];
    }

    public static function print(): void
    {
        echo 'Usage: php index.php [options]' . PHP_EOL;
        echo 'OPTIONS' . PHP_EOL;
        foreach (self::all() as $option) {
            echo '  ' . $option->getDescription() . PHP_EOL;
        }
        echo 'EXAMPLE:' . PHP_EOL;
        echo '  php index.php -p 53 -a 127.0.0.1 -z example.zone' . PHP_EOL;
    }
}
