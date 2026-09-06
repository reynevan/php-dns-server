<?php

namespace Reynevan\PhpDnsServer\Transport;

readonly class SocketAddress
{
    public function __construct(private string $address, private int $port)
    {
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
