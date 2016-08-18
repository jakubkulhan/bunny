<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->queueDeclare('hello', false, false, false, false);

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$channel->run(
    function (Message $message, Channel $channel, Client $client) {
        echo " [x] Received ", $message->content, "\n";
    },
    'hello',
    '',
    false,
    true
);
