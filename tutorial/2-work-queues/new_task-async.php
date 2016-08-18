<?php

use Bunny\Async\Client;
use Bunny\Channel;
use React\EventLoop\Factory;

require '../../vendor/autoload.php';

$loop = Factory::create();

$data = implode(' ', array_slice($argv, 1));
(new Client($loop))->connect()->then(function (Client $client) {
    return $client->channel();
})->then(function (Channel $channel) {
    return $channel->queueDeclare('task_queue', false, true, false, false)->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) use ($data) {
    echo " [x] Sending '{$data}'\n";
    return $channel->publish(
        $data,
        [
            'delivery_mode' => 2
        ],
        '',
        'task_queue'
    )->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) use ($data) {
    echo " [x] Sent '{$data}'\n";
    $client = $channel->getClient();
    return $channel->close()->then(function () use ($client) {
        return $client;
    });
})->then(function (Client $client) {
    $client->disconnect();
});

$loop->run();
