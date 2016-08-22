<?php

use Bunny\Client;

require '../../vendor/autoload.php';

$client = (new Client())->connect();
$channel = $client->channel();

$channel->queueDeclare('hello', false, false, false, false);

$channel->publish('Hello World!', [], '', 'hello');
echo " [x] Sent 'Hello World!'\n";

$channel->close();
$client->disconnect();
