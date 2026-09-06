<?php

namespace Reynevan\PhpDnsServer\Transport;

use Reynevan\PhpDnsServer\Message\Opt;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\Response;
use Socket;

readonly class UdpTransport implements Transport
{
    private const int PORT = 53;
    private const int MIN_PAYLOAD_SIZE = 512;

    public function __construct(private int $payloadSize = 1232, private float $timeout = 3.0)
    {
    }

    public function ask(string $address, Query $query): ?Response
    {
        $socket = $this->open($address);

        try {
            $this->send($socket, $query);

            $buffer = $this->receive($socket);
            if ($buffer === null) {
                return null;
            }

            return Response::fromBuffer($buffer);
        } finally {
            socket_close($socket);
        }
    }

    /**
     * @throws SocketException
     */
    private function open(string $address): Socket
    {
        $domain = str_contains($address, ':') ? AF_INET6 : AF_INET;

        $socket = @socket_create($domain, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            throw new SocketException(socket_strerror(socket_last_error()));
        }


        if (!@socket_connect($socket, $address, self::PORT)) {
            $error = socket_strerror(socket_last_error($socket));
            socket_close($socket);
            throw new SocketException($error);
        }

        $timeout = [
            'sec' => (int) $this->timeout,
            'usec' => (int) round(fmod($this->timeout, 1.0) * 1_000_000),
        ];
        if (!@socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeout)) {
            $error = socket_strerror(socket_last_error($socket));
            socket_close($socket);
            throw new SocketException($error);
        }

        return $socket;
    }

    /**
     * @throws SocketException
     */
    private function send(Socket $socket, Query $query): void
    {
        if ($this->receiveBufferSize() > self::MIN_PAYLOAD_SIZE) {
            $query = $query->withOpt(new Opt(payloadSize: $this->receiveBufferSize()));
        }

        $message = $query->encode();

        $sent = @socket_send($socket, $message, strlen($message), 0);
        if ($sent === false || $sent !== strlen($message)) {
            throw new SocketException(socket_strerror(socket_last_error($socket)));
        }
    }

    /**
     * @throws SocketException
     */
    private function receive(Socket $socket): ?string
    {
        socket_clear_error($socket);

        $buffer = '';
        $length = @socket_recv($socket, $buffer, $this->receiveBufferSize(), 0);

        if ($length === false) {
            $error = socket_last_error($socket);
            if (in_array($error, [SOCKET_EAGAIN, SOCKET_EWOULDBLOCK], true)) {
                return null;
            }
            throw new SocketException(socket_strerror($error));
        }

        if ($length < Response::HEADER_LENGTH) {
            return null;
        }

        return $buffer;
    }

    private function receiveBufferSize(): int
    {
        return max(self::MIN_PAYLOAD_SIZE, $this->payloadSize);
    }
}
