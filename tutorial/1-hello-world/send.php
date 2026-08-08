<?php

declare(strict_types=1);

use Bunny\Client;
use React\EventLoop\Loop;
use function React\Async\async;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
Loop::futureTick(async(static function () use ($client): void {
    $channel = $client->channel();
    $channel->queueDeclare('hello', durable: true);

    $channel->publish('Hello World!', [], '', 'hello');
    echo ' [x] Sent "Hello World!"' . PHP_EOL;

    $channel->close();
    $client->disconnect();
}));
