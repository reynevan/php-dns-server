<?php

namespace Reynevan\PhpDnsServer\Transport;

use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\Response;
use Reynevan\PhpDnsServer\Message\ResponseCode;
use Reynevan\PhpDnsServer\Resolver\NameServer;

class UdpTransport implements Transport
{

    public function ask(NameServer $ns, Query $query): Response
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $msg = $query->encode();


        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 3, 'usec' => 0]);
        socket_sendto($socket, $msg, strlen($msg), 0, $ns->getAddressA(), 53);
        $response = '';
        $from = '';
        $fromPort = 0;
        $bytes = socket_recvfrom($socket, $response, 4096, 0, $from, $fromPort);
        socket_close($socket);

        var_dump($bytes);
        var_dump($response);

        return Response::answer($query, [], ResponseCode::NOERROR);
    }
}