<?php

declare(strict_types=1);

use Bunny\Client;
use Bunny\Test\Library\Environment;
use function Bunny\Test\Library\parseAmqpUri;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = parseAmqpUri(Environment::getTestRabbitMqConnectionUri());
echo 'Waiting for RabbitMQ to be reachable';
while (true) {
    sleep(1);
    try {
        $client = new Client($options);
        $client->connect();
        $client->disconnect();
        break;
    } catch (Throwable) {
        echo '.';
    }
}

echo PHP_EOL, 'RabbitMQ reachable', PHP_EOL;
