<?php

use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Message;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

require '../../vendor/autoload.php';

$loop = Factory::create();

class FibonacciRpcClient
{
    private $channel;

    public function __construct(LoopInterface $loop)
    {
        $this->channel = (new Client($loop))->connect()->then(function (Client $client) {
            return $client->channel();
        });
    }

    public function call($n)
    {
        return $this->channel->then(function (Channel $channel) {
            return \React\Promise\all([
                $channel->queueDeclare('', false, false, true),
                \React\Promise\resolve($channel),
            ]);
        })->then(function ($values) use ($n) {
            list ($responseQueue, $channel) = $values;
            $corr_id = uniqid();
            $deferred = new Deferred();
            $channel->consume(
                function (Message $message, Channel $channel, Client $client) use ($deferred, $corr_id) {
                    if ($message->getHeader('correlation_id') != $corr_id) {
                        return;
                    }
                    $deferred->resolve((int)$message->content);
                    $client->disconnect();
                },
                $responseQueue->queue
            );
            $channel->publish(
                $n,
                [
                    'correlation_id' => $corr_id,
                    'reply_to' => $responseQueue->queue,
                ],
                '',
                'rpc_queue'
            );
            return $deferred->promise();
        });
    }
}

$fibonacci_rpc = new FibonacciRpcClient($loop);
$response = $fibonacci_rpc->call(30)->then(function ($n) {
    echo " [.] Got ", $n, "\n";
});

$loop->run();
