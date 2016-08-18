<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->exchangeDeclare('logs', 'fanout');
$queue = $channel->queueDeclare('', false, false, true, false);
$channel->queueBind($queue->queue, 'logs');

echo ' [*] Waiting for logs. To exit press CTRL+C', "\n";

$channel->run(
    function (Message $message, Channel $channel, Client $client) {
        echo ' [x] ', $message->content, "\n";
    },
    $queue->queue,
    '',
    false,
    true
);
