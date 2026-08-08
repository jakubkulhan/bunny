<?php

declare(strict_types=1);

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use React\EventLoop\Loop;
use function React\Async\async;

function fib(int $n): int
{
    if ($n === 0) {
        return 0;
    }

    if ($n === 1) {
        return 1;
    }

    return fib($n - 1) + fib($n - 2);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
Loop::futureTick(async(static function () use ($client): void {
    $channel = $client->channel();

    $channel->queueDeclare('rpc_queue', durable: true);

    echo ' [x] Awaiting RPC requests' . PHP_EOL;

    $channel->consume(
        async(static function (Message $message, Channel $channel, Client $client): void {
            $n = intval($message->content);
            echo ' [.] fib(' . $n . ')' . PHP_EOL;
            $channel->publish(
                (string) fib($n),
                [
                    'correlation_id' => $message->getHeader('correlation_id'),
                ],
                '',
                $message->getHeader('reply_to'),
            );
            $channel->ack($message);
        }),
        'rpc_queue',
    );
}));
