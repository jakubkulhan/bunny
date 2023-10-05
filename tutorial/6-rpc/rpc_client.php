<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use function React\Async\async;
use function React\Async\await;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

class FibonacciRpcClient
{
    private $client;
    private $channel;

    public function __construct()
    {
        $this->client = new Client();
        $this->channel = $this->client->channel();
    }

    public function close()
    {
        $this->client->disconnect();
    }

    public function call($n)
    {
        $corr_id = uniqid();
        $response = new Deferred();
        $responseQueue = $this->channel->queueDeclare('', false, false, true);
        $subscription = $this->channel->consume(
            function (Message $message, Channel $channel, Client $client) use (&$response, $corr_id, &$subscription) {
                if ($message->getHeader('correlation_id') != $corr_id) {
                    return;
                }
                $response->resolve($message->content);
                $channel->cancel($subscription->consumerTag);
            },
            $responseQueue->queue
        );
        $this->channel->publish(
            $n,
            [
                'correlation_id' => $corr_id,
                'reply_to' => $responseQueue->queue,
            ],
            '',
            'rpc_queue'
        );

        return (int) await($response->promise());
    }
}

$fibonacci_rpc = new FibonacciRpcClient();
$response = $fibonacci_rpc->call(30);
echo " [.] Got ", $response, "\n";
$fibonacci_rpc->close();
