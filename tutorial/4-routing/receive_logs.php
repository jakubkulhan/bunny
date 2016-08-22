<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->exchangeDeclare('direct_logs', 'direct');
$queue = $channel->queueDeclare('', false, false, true, false);

$severities = array_slice($argv, 1);
if(empty($severities )) {
    file_put_contents('php://stderr', "Usage: $argv[0] [info] [warning] [error]\n");
    exit(1);
}

foreach($severities as $severity) {
    $channel->queueBind($queue->queue, 'direct_logs', $severity);
}

echo ' [*] Waiting for logs. To exit press CTRL+C', "\n";

$channel->run(
    function (Message $message, Channel $channel, Client $client) {
        echo ' [x] ', $message->routingKey, ':', $message->content, "\n";
    },
    $queue->queue,
    '',
    false,
    true
);
