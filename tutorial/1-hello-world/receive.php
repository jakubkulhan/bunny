<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use React\EventLoop\Loop;
use function React\Async\async;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
$channel = $client->channel();
$channel->qos(0, 100);
$channel->queueDeclare('hello', false, false, false, false);

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$channel->consume(
    function (Message $message, Channel $channel) {
        echo " [x] Received ", $message->content, "\n";
        $channel->ack($message);
    },
    'hello',
    '',
    false,
    false,
);
