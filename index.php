<?php


use Reynevan\PhpDnsServer\Cli\InvalidOptionException;
use Reynevan\PhpDnsServer\Cli\Options;
use Reynevan\PhpDnsServer\Cli\ServerOptions;
use Reynevan\PhpDnsServer\DnsServer;
use Reynevan\PhpDnsServer\Transport\SocketException;

include './vendor/autoload.php';

try {
    $options = Options::parse($argv, ServerOptions::all());

    new DnsServer()->run($options);

} catch (InvalidOptionException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    fwrite(STDERR, 'Usage: php index.php [options]' . PHP_EOL);
    exit(2);
} catch (SocketException $e) {
    fwrite(STDERR, 'Socket error: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}