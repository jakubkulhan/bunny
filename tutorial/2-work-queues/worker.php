<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->queueDeclare('task_queue', false, true, false, false);

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$channel->qos(0, 1);
$channel->run(
    function (Message $message, Channel $channel, Client $client) {
        echo " [x] Received ", $message->content, "\n";
        sleep(substr_count($message->content, '.'));
        echo " [x] Done", "\n";
        $channel->ack($message);
    },
    'task_queue'
);
