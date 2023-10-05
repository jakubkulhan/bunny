<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
$channel = $client->channel();

$channel->exchangeDeclare('direct_logs', 'direct');
$queue = $channel->queueDeclare('', false, false, true, false);

$severities = array_slice($argv, 1);
if(empty($severities )) {
    file_put_contents('php://stderr', "Usage: $argv[0] [info] [warning] [error]\n");
    $client->disconnect();
    exit(1);
}

foreach($severities as $severity) {
    $channel->queueBind('direct_logs', $queue->queue, $severity);
}

echo ' [*] Waiting for logs. To exit press CTRL+C', "\n";

$channel->consume(
    function (Message $message, Channel $channel, Client $client) {
        echo ' [x] ', $message->routingKey, ':', $message->content, "\n";
    },
    $queue->queue,
    '',
    false,
    true
);
