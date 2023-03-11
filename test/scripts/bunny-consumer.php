#!/usr/bin/env php
<?php

// Usage: bunny-consumer.php <amqp-uri> <queue-name> <max-seconds>

declare(strict_types=1);

namespace Bunny\Test\App;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

use function Bunny\Test\Library\parseAmqpUri;

require __DIR__ . '/../../vendor/autoload.php';

function app(array $args)
{
    $connection = parseAmqpUri($args['amqpUri']);

    $client = new Client($connection);

    pcntl_signal(SIGINT, function () use ($client) {
        $client->disconnect()->done(function () use ($client) {
            $client->stop();
        });
    });

    $client->connect();
    $channel = $client->channel();

    $channel->qos(0, 1);
    $channel->queueDeclare($args['queueName']);
    $channel->consume(function (Message $message, Channel $channel) use ($client) {
        $channel->ack($message);
    });
    $client->run($args['maxSeconds'] > 0 ? $args['maxSeconds'] : null);
}

$argv_copy = $argv;

array_shift($argv_copy);

$args = [
    'amqpUri' => array_shift($argv_copy),
    'queueName' => array_shift($argv_copy),
    'maxSeconds' => (int) array_shift($argv_copy),
];

app($args);
