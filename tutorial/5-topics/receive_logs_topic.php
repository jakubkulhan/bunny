<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->exchangeDeclare('topic_logs', 'topic');
$queue = $channel->queueDeclare('', false, false, true, false);

$binding_keys = array_slice($argv, 1);
if(empty($binding_keys )) {
    file_put_contents('php://stderr', "Usage: $argv[0] [binding_key]\n");
    exit(1);
}

foreach($binding_keys as $binding_key) {
    $channel->queueBind($queue->queue, 'topic_logs', $binding_key);
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
