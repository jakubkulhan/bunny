<?php

use Bunny\Channel;
use Bunny\Async\Client;
use Bunny\Message;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use React\EventLoop\Factory;

require '../vendor/autoload.php';

$loop = Factory::create();
$channelRef = null;
$consumerTag = null;

$clientConfig = [
    "host" => "rabbitmq.example.com",
    "port" => 5672,
    "vhost" => "/",
    "user" => "appuser",
    "password" => "apppass",
];

$client = new Client($loop, $clientConfig);
$client->connect()->then(function (Client $client) {
    return $client->channel();
}, function($reason) {
    $reasonMsg = "";
    if (is_string($reason)) {
        $reasonMsg = $reason;
    } else if ($reason instanceof Throwable) {
        $reasonMsg = $reason->getMessage();
    }
    print "Rejected: {$reasonMsg}\n";
})->then(function (Channel $channel) {
    return $channel->qos(0, 1)->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) {
    return $channel->queueDeclare('test', false, true, false, false)->then(function () use ($channel) {
        return $channel;
    });
})->then(function (Channel $channel) use (&$channelRef) {
    $channelRef = $channel;
    echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";
    $channel->consume(
        function (Message $message, Channel $channel, Client $client) {
            echo " [x] Received ", $message->content, "\n";

            // Do some work - we generate password hashes with a high cost
            // sleep() gets interrupted by Ctrl+C so it's not very good for demos
            // Performing multiple work units demonstrates that nothing is skipped
            for ($i = 0; $i < 3; $i++) {
                print "WU {$i}\n";
                password_hash(random_bytes(255), PASSWORD_BCRYPT, ["cost" => 15]);
            }
            echo " [x] Done ", $message->content, "\n";

            $channel->ack($message)->then(function() use ($message) {
                print "ACK :: {$message->content}\n";
            }, function($reason) {
                $reasonMsg = "";
                if (is_string($reason)) {
                    $reasonMsg = $reason;
                } else if ($reason instanceof Throwable) {
                    $reasonMsg = $reason->getMessage();
                }
                print "ACK FAILED! - {$reasonMsg}\n";
            })->done();
        },
        'test'
    )->then(function (MethodBasicConsumeOkFrame $response) use (&$consumerTag) {
        $consumerTag = $response->consumerTag;
    })->done();

})->done();

// Capture signals - SIGINT = Ctrl+C; SIGTERM = `kill`
$loop->addSignal(SIGINT, function (int $signal) use (&$channelRef, &$consumerTag) {
    print "Consumer cancelled\n";
    $channelRef->cancel($consumerTag)->done(function() {
        exit();
    });
});
$loop->addSignal(SIGTERM, function (int $signal) use (&$channelRef, &$consumerTag) {
    print "Consumer cancelled\n";
    $channelRef->cancel($consumerTag)->done(function() {
        exit();
    });
});

$loop->run();
