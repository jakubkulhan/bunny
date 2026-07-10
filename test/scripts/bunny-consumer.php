#!/usr/bin/env php
<?php

// Usage: bunny-consumer.php <amqp-uri> <queue-name> <max-seconds>

declare(strict_types=1);

namespace Bunny\Test\App;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use React\EventLoop\Loop;
use RuntimeException;
use function Bunny\Test\Library\parseAmqpUri;
use function array_shift;
use function count;
use function pcntl_signal;
use const SIGINT;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * @param array<string,mixed> $args
 */
function app(array $args): void
{
    $connection = parseAmqpUri($args['amqpUri']);

    $client = new Client($connection);

    pcntl_signal(SIGINT, static function () use ($client): void {
        $client->disconnect();
    });

    $client->connect();
    $channel = $client->channel();

    $channel->qos(0, 1);
    $channel->queueDeclare($args['queueName'], durable: true);
    $channel->consume(static function (Message $message, Channel $channel): void {
        $channel->ack($message);
    });
    Loop::addTimer($args['maxSeconds'], static function () use ($client): void {
        $client->disconnect();
    });
}

if (!isset($argv)) {
    throw new RuntimeException('No argv found');
}

if (count($argv) < 4) {
    throw new RuntimeException('Expecting at least 3 arguments, got ' . (count($argv) - 1));
}

$argvCopy = $argv;

array_shift($argvCopy);

$args = [
    'amqpUri' => array_shift($argvCopy),
    'queueName' => array_shift($argvCopy),
    'maxSeconds' => (int) array_shift($argvCopy),
];

app($args);
