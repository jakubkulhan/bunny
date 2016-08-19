<?php

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;

require '../../vendor/autoload.php';

class FibonacciRpcClient
{
    private $client;
    private $channel;

    public function __construct()
    {
        $this->client = (new Client())->connect();
        $this->channel = $this->client->channel();
    }

    public function call($n)
    {
        $corr_id = uniqid();
        $response = null;
        $responseQueue = $this->channel->queueDeclare('', false, false, true);
        $this->channel->consume(
            function (Message $message, Channel $channel, Client $client) use (&$response, $corr_id) {
                if ($message->getHeader('correlation_id') != $corr_id) {
                    return;
                }
                $response = $message->content;
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
        while ($response === null) {
            $this->client->run(0.01);
        }
        return (int) $response;
    }
}

$fibonacci_rpc = new FibonacciRpcClient();
$response = $fibonacci_rpc->call(30);
echo " [.] Got ", $response, "\n";
