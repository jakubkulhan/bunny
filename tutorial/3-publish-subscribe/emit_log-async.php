<?php

use Bunny\Async\Client;
use Bunny\Channel;
use React\EventLoop\Factory;

require '../../vendor/autoload.php';

$loop = Factory::create();

$data = implode(' ', array_slice($argv, 1));
if (empty($data)) {
    $data = "info: Hello World!";
}

(new Client($loop))->connect()->then(function (Client $client) {
    return $client->channel();
})->then(function (Channel $channel) {
    return $channel->exchangeDeclare('logs', 'fanout')->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) use ($data) {
    echo " [x] Sending '{$data}'\n";
    return $channel->publish($data, [], 'logs')->then(function () use ($channel) {
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
