<?php

namespace Reynevan\PhpDnsServer\Transport;

readonly class Datagram
{
    public function __construct(private string $buffer, private SocketAddress $peer)
    {
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function getPeer(): SocketAddress
    {
        return $this->peer;
    }
}
