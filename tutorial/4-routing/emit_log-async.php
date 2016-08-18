<?php

use Bunny\Async\Client;
use Bunny\Channel;
use React\EventLoop\Factory;

require '../../vendor/autoload.php';

$loop = Factory::create();

$severity = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'info';
$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}

(new Client($loop))->connect()->then(function (Client $client) {
    return $client->channel();
})->then(function (Channel $channel) {
    return $channel->exchangeDeclare('direct_logs', 'direct')->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) use ($data, $severity) {
    echo " [x] Sending ",$severity,':',$data," \n";
    return $channel->publish($data, [], 'direct_logs', $severity)->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) use ($data, $severity) {
    echo " [x] Sent ",$severity,':',$data," \n";
    $client = $channel->getClient();
    return $channel->close()->then(function () use ($client) {
        return $client;
    });
})->then(function (Client $client) {
    $client->disconnect();
});

$loop->run();
