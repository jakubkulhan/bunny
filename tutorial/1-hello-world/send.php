<?php

use Bunny\Client;
use React\EventLoop\Loop;
use React\Promise\Promise;
use function React\Async\async;
use function React\Async\await;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$client = new Client();
$channel = $client->channel();
$channel->queueDeclare('hello', false, false, false, false);
$channel->close();

$keepRunning = true;

Loop::futureTick(async(function () use ($client, &$keepRunning) {
    $channel = $client->channel();
    while ($keepRunning) {
        $channel->publish('Hello World!', [], '', 'hello');
    }
}));

//$timer = Loop::addPeriodicTimer(60, static function () use ($client): void {
//    file_put_contents('var/client.' . time() . '.stop.txt', var_export($client, true));
//    file_put_contents('var/loop.' . time() . '.stop.txt', var_export(Loop::get(), true));
//});

Loop::addTimer(60 * 60 * 6, static function () use (&$keepRunning, $client): void {
    $keepRunning = false;
    file_put_contents('var/client.pre.stop.txt', var_export($client, true));
    file_put_contents('var/loop.pre.stop.txt', var_export(Loop::get(), true));
});

Loop::addTimer((60 * 60 * 6) + 3, static function () use ($client/*, $timer*/): void {
    file_put_contents('var/client.post.stop.txt', var_export($client, true));
    file_put_contents('var/loop.post.stop.txt', var_export(Loop::get(), true));

//    Loop::cancelTimer($timer);
});
