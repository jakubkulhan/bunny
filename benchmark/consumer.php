<?php
namespace Bunny;

require_once __DIR__ . "/../vendor/autoload.php";

$c = new Client();
$ch = $c->connect()->channel();

$ch->queueDeclare("bench_queue");
$ch->exchangeDeclare("bench_exchange");
$ch->queueBind("bench_queue", "bench_exchange");

$t = null;
$count = 0;

$ch->run(function (Message $msg, Channel $ch, Client $c) use (&$t, &$count) {
    if ($t === null) {
        $t = microtime(true);
    }

    if ($msg->content === "quit") {
        printf("Pid: %s, Count: %s, Time: %.4f\n", getmypid(), $count, microtime(true) - $t);
        $c->stop();
    } else {
        ++$count;
    }

}, "bench_queue", "", false, true);

$c->disconnect();
