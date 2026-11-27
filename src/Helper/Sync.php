<?php

declare(strict_types=1);

namespace Bunny\Helper;

use Bunny\Message;

final class Sync
{
    private const TIMEOUT = PublishMessage::TIMEOUT;

    public function __construct(
        private readonly Async $async,
    ) {
    }

    /**
     * Open connection, published message to given exchange, and close the connection before returning.
     *
     * @param array<string,mixed> $headers
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
        $message = new PublishMessage(
            $body,
            $headers,
            $exchange,
            $routingKey,
            $mandatory,
            $immediate,
        );

        return $this->async->publish(
            $message->body,
            $message->headers,
            $message->exchange,
            $message->routingKey,
            $message->mandatory,
            $message->immediate,
            $timeout,
        );
    }

    /**
     * Open connection, get a single message (or nothing if it's empty) from a queue, and close the connection before returning.
     */
    public function get(
        string $queue = '',
        bool $noAck = false,
        float $timeout = self::TIMEOUT,
    ): ?Message {
        return $this->async->get($queue, $noAck, $timeout);
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
        yield from $this->async->iterate($queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments, $concurrency);
    }
}
