<?php

use Bunny\Client;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
$channel = $client->channel();

$channel->exchangeDeclare('logs', 'fanout');

$data = implode(' ', array_slice($argv, 1));
$channel->publish($data, [], 'logs');
echo " [x] Sent '{$data}'\n";

$channel->close();
$client->disconnect();
