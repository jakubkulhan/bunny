<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

function fib($n) {
    if ($n == 0)
        return 0;
    if ($n == 1)
        return 1;
    return fib($n-1) + fib($n-2);
}

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->queueDeclare('rpc_queue');

echo " [x] Awaiting RPC requests\n";

$channel->run(
    function (Message $message, Channel $channel, Client $client) {
        $n = intval($message->content);
        echo " [.] fib(", $n, ")\n";
        $channel->publish(
            (string) fib($n),
            [
                'correlation_id' => $message->getHeader('correlation_id'),
            ],
            '',
            $message->getHeader('reply_to')
        );
        $channel->ack($message);
    },
    'rpc_queue'
);
