<?php

declare(strict_types=1);

namespace Bunny\Helper;

use Bunny\ChannelInterface;
use Bunny\ClientInterface;
use Bunny\Message;
use Iterator;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use SplQueue;
use function React\Async\async;
use function React\Async\await;

/**
 * @implements Iterator<mixed, Message>
 */
final class IterableSubscription implements Iterator
{
    /**
     * @var SplQueue<Message>
     */
    private readonly SplQueue $queue;
    private readonly ChannelInterface $channel;
    private readonly string $consumerTag;
    /**
     * @var Deferred<bool>|null
     */
    private Deferred|null $valid = null;
    private bool $completed      = false;
    private int $key             = 0;

    /**
     * @param array<string,mixed> $arguments
     * @param positive-int        $concurrency
     */
    public function __construct(
        private readonly ClientInterface $client,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $nowait = false,
        array $arguments = [],
        int $concurrency = 1,
    ) {
        $this->queue      = new SplQueue();
        $this->channel = $this->client->channel();
        $this->consumerTag = $this->channel->consume(
            function (mixed $value): void {
                $this->push($value);
            },
            $queue,
            $consumerTag,
            $noLocal,
            $noAck,
            $exclusive,
            $nowait,
            $arguments,
            $concurrency,
        )->consumerTag;
    }

    public function __destruct()
    {
        $this->break();
    }

    public function break(): void
    {
        Loop::futureTick(async(function (): void {
            $this->channel->cancel($this->consumerTag);
        }));
        $this->completed = true;
    }

    private function push(mixed $value): void
    {
        $this->queue->enqueue($value);
        if ($this->valid === null) {
            return;
        }

        $valid       = $this->valid;
        $this->valid = null;
        $valid->resolve(true);
    }

    // phpcs:disable
    /**
     * @return mixed
     */
    public function current(): mixed
    {
        return $this->queue->dequeue();
    }
    // phpcs:enable

    public function next(): void
    {
        // no-op
    }

    // phpcs:disable
    /**
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->key++;
    }
    // phpcs:enable

    public function valid(): bool
    {
        if ($this->queue->count() > 0) {
            return true;
        }

        if (!$this->completed) {
            $this->valid = new Deferred();

            return await($this->valid->promise());
        }

        return false;
    }

    public function rewind(): void
    {
        // no-op
    }
}
