<?php

use Bunny\Client;

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->queueDeclare('task_queue', false, true, false, false);

$data = implode(' ', array_slice($argv, 1));
$channel->publish(
    $data,
    [
        'delivery_mode' => 2
    ],
    '',
    'task_queue'
);
echo " [x] Sent '{$data}'\n";

$channel->close();
$client->disconnect();
