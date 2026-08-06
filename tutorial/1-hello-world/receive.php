<?php

declare(strict_types=1);

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use React\EventLoop\Loop;
use function React\Async\async;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
Loop::futureTick(async(static function () use ($client): void {
    $channel = $client->channel();

    $channel->queueDeclare('hello', durable: true);

    echo ' [*] Waiting for messages. To exit press CTRL+C', PHP_EOL;

    $channel->consume(
        static function (Message $message, Channel $channel): void {
            echo ' [x] Received ' . $message->content . PHP_EOL;
        },
        'hello',
        '',
        false,
        true,
    );
}));
