<?php

use Bunny\Async\Client;
use Bunny\Channel;
use React\EventLoop\Factory;

require '../../vendor/autoload.php';

$loop = Factory::create();

(new Client($loop))->connect()->then(function (Client $client) {
    return $client->channel();
})->then(function (Channel $channel) {
    return $channel->queueDeclare('hello', false, false, false, false)->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) {
    echo " [x] Sending 'Hello World!'\n";
    return $channel->publish('Hello World!', [], '', 'hello')->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) {
    echo " [x] Sent 'Hello World!'\n";
    $client = $channel->getClient();
    return $channel->close()->then(function () use ($client) {
        return $client;
    });
})->then(function (Client $client) {
    $client->disconnect();
});

$loop->run();