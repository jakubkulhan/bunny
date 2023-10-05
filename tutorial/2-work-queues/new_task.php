<?php

use Bunny\Client;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
$channel = $client->channel();

$channel->queueDeclare('task_queue', false, true, false, false);

$data = implode(' ', array_slice($argv, 1));
$channel->publish(
    $data,
    [
        'delivery-mode' => 2
    ],
    '',
    'task_queue'
);
echo " [x] Sent '{$data}'\n";

$channel->close();
$client->disconnect();
