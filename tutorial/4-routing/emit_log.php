<?php

use Bunny\Client;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
$channel = $client->channel();

$channel->exchangeDeclare('direct_logs', 'direct');

$severity = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'info';
$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}

$channel->publish($data, [], 'direct_logs', $severity);
echo " [x] Sent ",$severity,':',$data," \n";

$channel->close();
$client->disconnect();
