<?php

declare(strict_types=1);

namespace Bunny\Helper;

use Bunny\Client;
use Bunny\ClientInterface;
use Bunny\Configuration;
use Bunny\Message;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;
use function React\Promise\race;

final class Async
{
    private const TIMEOUT = PublishMessage::TIMEOUT;
    private readonly CLientInterface $client;

    public function __construct(
        Configuration|ClientInterface $configurationOrClient,
    ) {
        if ($configurationOrClient instanceof Configuration) {
            $this->client = new Client($configurationOrClient);
        } else {
            $this->client = $configurationOrClient;
        }
    }

    /**
     * Open connection, published message to given exchange, and close the connection before returning.
     *
     * @param array<string,mixed> $headers
     *
     * @return int|false
     */
    public function publish(
        string $body,
        array $headers = [],
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        float $timeout = self::TIMEOUT,
    ): int|bool {
        $promises = [];
        $result = $promises[] = async(function (
            PublishMessage $message,
        ): int|bool {
            $channel = $this->client->channel();
            $outCome = $channel->publish($message->body, $message->headers, $message->exchange, $message->routingKey, $message->mandatory, $message->immediate);
            Loop::futureTick(async(function (): bool {
                $this->client->disconnect();

                return true;
            }));

            return $outCome;
        })(
            new PublishMessage(
                $body,
                $headers,
                $exchange,
                $routingKey,
                $mandatory,
                $immediate,
            ),
        );
        $promises[] = sleep($timeout);

        await(race($promises));

        return await($result);
    }

    /**
     * Open connection, get a single message (or nothing if it's empty) from a queue, and close the connection before returning.
     */
    public function get(
        string $queue = '',
        bool $noAck = false,
        float $timeout = self::TIMEOUT,
    ): Message|null {
        $promises = [];
        $result = $promises[] = async(function (
            string $queue,
            bool $noAck,
        ): Message|null {
            $channel = $this->client->channel();
            $outCome = $channel->get($queue, $noAck);
            Loop::futureTick(async(function (): bool {
                $this->client->disconnect();

                return true;
            }));

            return $outCome;
        })(
            $queue,
            $noAck,
        );
        $promises[] = sleep($timeout);

        await(race($promises));

        return await($result);
    }

    /**
     * Open connection, get a single message (or nothing if it's empty) from a queue, and close the connection before returning.
     *
     * @param array<string,mixed> $arguments
     * @param positive-int        $concurrency
     *
     * @return iterable<Message>
     */
    public function iterate(
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $nowait = false,
        array $arguments = [],
        int $concurrency = 1,
    ): iterable {
        return new IterableSubscription($this->client, $queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments, $concurrency);
    }
}
