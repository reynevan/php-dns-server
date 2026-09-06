<?php

namespace Reynevan\PhpDnsServer\Transport;

use Socket;

readonly class UdpSocket
{
    private const int RECEIVE_BUFFER_SIZE = 4096;

    private Socket $socket;


    /**
     * @throws SocketException
     */
    private function __construct(string $address, int $port)
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            throw new SocketException(socket_strerror(socket_last_error()));
        }
        $this->socket = $socket;
        if (!@socket_bind($this->socket, $address, $port)) {
            throw new SocketException(socket_strerror(socket_last_error($this->socket)));
        }
    }

    /**
     * @throws SocketException
     */
    public static function bind(string $address, int $port): self
    {
        return new self($address, $port);
    }

    public function receive(?float $timeout = null): ?Datagram
    {
        $buffer = '';
        $clientIp = '';
        $clientPort = 0;

        $length = socket_recvfrom($this->socket, $buffer, self::RECEIVE_BUFFER_SIZE, 0, $clientIp, $clientPort);

        if ($length === false || strlen($buffer) < 2) {
            return null;
        }

        return new Datagram($buffer, new SocketAddress($clientIp, $clientPort));
    }

    public function send(string $data, SocketAddress $peer): void
    {
        socket_sendto($this->socket, $data, strlen($data), 0, $peer->getAddress(), $peer->getPort());
    }
}
