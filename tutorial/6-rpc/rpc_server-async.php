<?php

use Bunny\Channel;
use Bunny\Async\Client;
use Bunny\Message;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use React\EventLoop\Factory;

require '../../vendor/autoload.php';

function fib($n) {
    if ($n == 0)
        return 0;
    if ($n == 1)
        return 1;
    return fib($n-1) + fib($n-2);
}

$loop = Factory::create();

(new Client($loop))->connect()->then(function (Client $client) {
    return $client->channel();
})->then(function (Channel $channel) {
    return $channel->queueDeclare('rpc_queue')->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) {
    echo " [x] Awaiting RPC requests\n";
    $channel->consume(
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
            )->then(function () use ($channel, $message) {
                $channel->ack($message);
            });
        },
        'rpc_queue'
    );
});

$loop->run();
