<?php

declare(strict_types=1);

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use React\EventLoop\Loop;
use function React\Async\async;

require dirname(__DIR__) . '/vendor/autoload.php';

$channel = null;
$consumerTag = null;

// Capture signals - SIGINT = Ctrl+C; SIGTERM = `kill`
Loop::addSignal(SIGINT, async(static function (int $signal) use (&$channel, &$consumerTag): void {
    print 'Consumer cancelled\n';
    $channel->cancel($consumerTag);

    Loop::addTimer(3, static function (): void {
        Loop::stop();
    });
}));
Loop::addSignal(SIGTERM, async(static function (int $signal) use (&$channel, &$consumerTag): void {
    print 'Consumer cancelled\n';
    $channel->cancel($consumerTag);

    Loop::addTimer(3, static function (): void {
        Loop::stop();
    });
}));

$clientConfig = [
    'host' => 'rabbitmq.example.com',
    'port' => 5672,
    'vhost' => '/',
    'user' => 'appuser',
    'password' => 'apppass',
];

$client = new Client($clientConfig);
$channel = $client->channel();
$channel->qos(0, 13);
$channel->queueDeclare('hello', durable: true);
$channelRef = $channel;
echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";
$response = $channel->consume(
    async(static function (Message $message, Channel $channel): void {
        echo ' [x] Received ', $message->content, "\n";

        // Do some work - we generate password hashes with a high cost
        // sleep() gets interrupted by Ctrl+C so it's not very good for demos
        // Performing multiple work units demonstrates that nothing is skipped
        for ($i = 0; $i < 3; $i++) {
            print 'WU {$i}\n';
            password_hash(random_bytes(255), PASSWORD_BCRYPT, ['cost' => 15]);
        }

        echo ' [x] Done ', $message->content, "\n";

        $channel->ack($message);
    }),
    'hello',
    noAck: true,
);
$consumerTag = $response->consumerTag;
