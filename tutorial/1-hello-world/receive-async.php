<?php

use Bunny\Channel;
use Bunny\Async\Client;
use Bunny\Message;
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
    echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";
    $channel->consume(
        function (Message $message, Channel $channel, Client $client) {
            echo " [x] Received ", $message->content, "\n";
        },
        'hello',
        '',
        false,
        true
    );
});

$loop->run();
