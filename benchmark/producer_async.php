<?php
namespace Bunny;

use Bunny\Async\Client;
use React\Promise;

require_once __DIR__ . "/../vendor/autoload.php";

$c = new Client();

$body = <<<EOT
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyza
EOT;

$time = microtime(true);
$max = isset($argv[1]) ? (int) $argv[1] : 1;

$c->connect()->then(function (Client $c) {
    return $c->channel();

})->then(function (Channel $ch) {
    return Promise\all([
        $ch,
        $ch->queueDeclare("bench_queue"),
        $ch->exchangeDeclare("bench_exchange"),
        $ch->queueBind("bench_queue", "bench_exchange"),
    ]);

})->then(function ($r) use ($body, &$time, &$max) {
    /** @var Channel $ch */
    $ch = $r[0];

    $promises = [];

    for ($i = 0; $i < $max; $i++) {
        $promises[] = $ch->publish($body, [], "bench_exchange");
    }

    $promises[] = $ch->publish("quit", [], "bench_exchange");

    return Promise\all($promises);

})->then(function () use ($c) {
    return $c->disconnect();

})->then(function () use ($c, &$time) {
    echo microtime(true) - $time, "\n";
    $c->stop();
});

$c->run();
