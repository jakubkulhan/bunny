<?php

declare(strict_types=1);

use Bunny\Client;
use React\EventLoop\Loop;
use function React\Async\async;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client();
Loop::futureTick(async(static function () use ($argv, $client): void {
    $channel = $client->channel();

    $channel->queueDeclare('bench_queue', durable: true);
    $channel->exchangeDeclare('bench_exchange');
    $channel->queueBind('bench_exchange', 'bench_queue');

    $body = <<<'EOT'
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

    for ($i = 0; $i < $max; $i++) {
        $channel->publish($body, [], 'bench_exchange');
    }

    $runTime = microtime(true) - $time;
    printf("Produce: Pid: %s, Time: %.6f, Msg/sec: %.0f\n", getmypid(), $runTime, 1 / $runTime * $max);

    $channel->publish('quit', [], 'bench_exchange');

    $client->disconnect();
}));
