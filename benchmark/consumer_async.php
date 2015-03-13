<?php
namespace Bunny;

use Bunny\Async\Client;
use React\Promise;

require_once __DIR__ . "/../vendor/autoload.php";

$c = new Client();

$c->connect()->then(function (Client $c) {
    return $c->channel();

})->then(function (Channel $ch) {
    return Promise\all([
        $ch,
        $ch->queueDeclare("bench_queue"),
        $ch->exchangeDeclare("bench_exchange"),
        $ch->queueBind("bench_queue", "bench_exchange"),
    ]);

})->then(function ($r) {
    /** @var Channel $ch */
    $ch = $r[0];

    $t = null;
    $count = 0;

    return $ch->consume(function (Message $msg, Channel $ch, Client $c) use (&$t, &$count) {
        if ($t === null) {
            $t = microtime(true);
        }

        if ($msg->content === "quit") {
            printf("Pid: %s, Count: %s, Time: %.4f\n", getmypid(), $count, microtime(true) - $t);
            $c->disconnect()->then(function () use ($c){
                $c->stop();
            });

        } else {
            ++$count;
        }
    }, "bench_queue", "", false, true);
});

$c->run();
