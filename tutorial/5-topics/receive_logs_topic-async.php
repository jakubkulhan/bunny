<?php

use Bunny\Channel;
use Bunny\Async\Client;
use Bunny\Message;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use React\EventLoop\Factory;

require '../../vendor/autoload.php';

$loop = Factory::create();

$binding_keys = array_slice($argv, 1);
if(empty($binding_keys )) {
    file_put_contents('php://stderr', "Usage: $argv[0] [binding_key]\n");
    exit(1);
}

(new Client($loop))->connect()->then(function (Client $client) {
    return $client->channel();
})->then(function (Channel $channel) use ($binding_keys) {
    return $channel->exchangeDeclare('topic_logs', 'topic')->then(function () use ($channel, $binding_keys) {
        return $channel->queueDeclare('', false, false, true, false);
    })->then(function (MethodQueueDeclareOkFrame $frame) use ($channel, $binding_keys) {
        $promises = [];

        foreach($binding_keys as $binding_key) {
            $promises[] = $channel->queueBind($frame->queue, 'topic_logs', $binding_key);
        }

        return \React\Promise\all($promises)->then(function () use ($frame) {
            return $frame;
        });
    })->then(function (MethodQueueDeclareOkFrame $frame) use ($channel) {
        echo ' [*] Waiting for logs. To exit press CTRL+C', "\n";
        $channel->consume(
            function (Message $message, Channel $channel, Client $client) {
                echo ' [x] ', $message->content, "\n";
            },
            $frame->queue,
            '',
            false,
            true
        );
    });
});

$loop->run();
