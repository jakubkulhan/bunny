<?php

use Bunny\Client;

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->exchangeDeclare('topic_logs', 'topic');

$routing_key = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'info';
$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}

$channel->publish($data, [], 'topic_logs', $routing_key);
echo " [x] Sent ",$routing_key,':',$data," \n";

$channel->close();
$client->disconnect();
