<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
$channel = $client->channel();

$channel->exchangeDeclare('logs', 'fanout');
$queue = $channel->queueDeclare('', false, false, true, false);
$channel->queueBind('logs', $queue->queue);

echo ' [*] Waiting for logs. To exit press CTRL+C', "\n";

$channel->consume(
    function (Message $message, Channel $channel, Client $client) {
        echo ' [x] ', $message->content, "\n";
    },
    $queue->queue,
    '',
    false,
    true
);
