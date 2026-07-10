<?php

declare(strict_types=1);

namespace Bunny;

use Bunny\Exception\ChannelException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicCancelOkFrame;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicDeliverFrame;
use Bunny\Protocol\MethodBasicGetEmptyFrame;
use Bunny\Protocol\MethodBasicGetOkFrame;
use Bunny\Protocol\MethodBasicNackFrame;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Protocol\MethodChannelCloseFrame;
use Bunny\Protocol\MethodChannelCloseOkFrame;
use Bunny\Protocol\MethodConfirmSelectOkFrame;
use Bunny\Protocol\MethodFrame;
use Bunny\Protocol\MethodTxCommitOkFrame;
use Bunny\Protocol\MethodTxRollbackOkFrame;
use Bunny\Protocol\MethodTxSelectOkFrame;
use Evenement\EventEmitterTrait;
use LogicException;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use SplQueue;
use Throwable;
use function React\Async\async;
use function React\Async\await;
use function array_key_exists;
use function array_splice;
use function gettype;
use function sprintf;

/**
 * AMQP channel.
 *
 * - Closely works with underlying client instance.
 * - Manages consumers.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 * @final Will be marked final in a future major release
 */
class Channel implements ChannelInterface
{
    use EventEmitterTrait;
    use ChannelMethods {
        ChannelMethods::consume as private consumeImpl;
        ChannelMethods::ack as private ackImpl;
        ChannelMethods::reject as private rejectImpl;
        ChannelMethods::nack as private nackImpl;
        ChannelMethods::get as private getImpl;
        ChannelMethods::publish as private publishImpl;
        ChannelMethods::cancel as private cancelImpl;
        ChannelMethods::txSelect as private txSelectImpl;
        ChannelMethods::txCommit as private txCommitImpl;
        ChannelMethods::txRollback as private txRollbackImpl;
        ChannelMethods::confirmSelect as private confirmSelectImpl;
    }

    /** @var list<callable(\Bunny\Message, \Bunny\Protocol\MethodBasicReturnFrame): void> */
    private array $returnCallbacks = [];

    /** @var array<string,int> */
    private array $consumeConcurrency = [];

    /** @var array<string,int> */
    private array $consumeConcurrent = [];

    /** @var array<string,callable(\Bunny\Message, \Bunny\Channel, \Bunny\Client): (\React\Promise\PromiseInterface<mixed>|void)> */
    private array $deliverCallbacks = [];

    /** @var array<string,\SplQueue<\Bunny\Message>> */
    private array $deliveryQueue = [];

    /** @var list<callable(\Bunny\Protocol\MethodBasicAckFrame|\Bunny\Protocol\MethodBasicNackFrame): void> */
    private array $ackCallbacks = [];

    private ?MethodBasicReturnFrame $returnFrame = null;

    private ?MethodBasicDeliverFrame $deliverFrame = null;

    private ?ContentHeaderFrame $headerFrame = null;

    private int $bodySizeRemaining;

    private Buffer $bodyBuffer;

    private ChannelState $state = ChannelState::Ready;

    private ChannelMode $mode = ChannelMode::Regular;

    /** @var \React\Promise\Deferred<int>|null */
    private ?Deferred $closeDeferred = null;

    /** @var \React\Promise\PromiseInterface<void>|null */
    private ?PromiseInterface $closePromise = null;

    private ?int $deliveryTag = null;

    public function __construct(private Connection $connection, private Client $client, public readonly int $channelId)
    {
        $this->bodyBuffer = new Buffer();
    }

    protected function getClient(): Connection
    {
        if ($this->state === ChannelState::Error) {
            throw new ChannelException('Channel in error state.');
        }

        if ($this->state === ChannelState::Closing) {
            throw new ChannelException('Channel is closing');
        }

        if ($this->state === ChannelState::Closed) {
            throw new ChannelException('Channel is closed');
        }

        return $this->connection;
    }

    /**
     * Returns channel id.
     */
    public function getChannelId(): int
    {
        return $this->channelId;
    }

    /**
     * Returns the channel mode.
     */
    public function getMode(): ChannelMode
    {
        return $this->mode;
    }

    /**
     * Listener is called whenever 'basic.return' frame is received with arguments (Message $returnedMessage, MethodBasicReturnFrame $frame)
     *
     * @param callable(\Bunny\Message, \Bunny\Protocol\MethodBasicReturnFrame): void $callback
     */
    public function addReturnListener(callable $callback): self
    {
        $this->removeReturnListener($callback); // remove if previously added to prevent calling multiple times
        $this->returnCallbacks[] = $callback;

        return $this;
    }

    /**
     * Removes registered return listener. If the callback is not registered, this is noop.
     */
    public function removeReturnListener(callable $callback): self
    {
        foreach ($this->returnCallbacks as $k => $v) {
            if ($v === $callback) {
                array_splice($this->returnCallbacks, $k, 1);
            }
        }

        return $this;
    }

    /**
     * Listener is called whenever 'basic.ack' or 'basic.nack' is received.
     *
     * @param callable(\Bunny\Protocol\MethodBasicAckFrame|\Bunny\Protocol\MethodBasicNackFrame): void $callback
     */
    public function addAckListener(callable $callback): self
    {
        if ($this->mode !== ChannelMode::Confirm) {
            throw new ChannelException('Ack/nack listener can be added when channel in confirm mode.');
        }

        $this->removeAckListener($callback);
        $this->ackCallbacks[] = $callback;

        return $this;
    }

    /**
     * Removes registered ack/nack listener. If the callback is not registered, this is noop.
     */
    public function removeAckListener(callable $callback): self
    {
        if ($this->mode !== ChannelMode::Confirm) {
            throw new ChannelException('Ack/nack listener can be removed when channel in confirm mode.');
        }

        foreach ($this->ackCallbacks as $k => $v) {
            if ($v === $callback) {
                array_splice($this->ackCallbacks, $k, 1);
            }
        }

        return $this;
    }

    /**
     * Closes channel.
     *
     * Always returns a promise, because there can be outstanding messages to be processed.
     */
    public function close(int $replyCode = 0, string $replyText = '', bool $connectionStatus = ClientInterface::RAW_CONNECTION_ACTIVE): void
    {
        if ($this->state === ChannelState::Closed) {
            throw new ChannelException(sprintf('Trying to close already closed channel #%d.', $this->channelId));
        }

        if ($this->state === ChannelState::Closing) {
            await($this->closePromise);

            return;
        }

        if ($connectionStatus === ClientInterface::RAW_CONNECTION_INACTIVE) {
            $this->onClose();

            return;
        }

        $this->state = ChannelState::Closing;

        $this->connection->channelClose($this->channelId, $replyCode, 0, 0, $replyText);
        $this->closeDeferred = new Deferred();
        $this->closePromise = $this->closeDeferred->promise();

        await($this->closePromise);
    }

    /**
     * Creates new consumer on channel.
     *
     * @param array<string,mixed> $arguments
     * @param positive-int        $concurrency
     */
    public function consume(callable $callback, string $queue = '', string $consumerTag = '', bool $noLocal = false, bool $noAck = false, bool $exclusive = false, bool $nowait = false, array $arguments = [], int $concurrency = 1): MethodBasicConsumeOkFrame
    {
        if ($concurrency <= 0) {
            throw new ChannelException(
                'basic.consume concurrency must be 1 or higher',
            );
        }

        $response = $this->consumeImpl($queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);

        if ($response instanceof MethodBasicConsumeOkFrame) {
            $this->consumeConcurrency[$response->consumerTag] = $concurrency;
            $this->consumeConcurrent[$response->consumerTag] = 0;
            $this->deliverCallbacks[$response->consumerTag] = $callback;
            $this->deliveryQueue[$response->consumerTag] = new SplQueue();

            return $response;
        }

        throw new ChannelException(
            'basic.consume unexpected response of type ' . gettype($response) . '.',
        );
    }

    /**
     * Acks given message.
     */
    public function ack(Message $message, bool $multiple = false): bool
    {
        return $this->ackImpl($message->deliveryTag, $multiple);
    }

    /**
     * Nacks given message.
     */
    public function nack(Message $message, bool $multiple = false, bool $requeue = true): bool
    {
        return $this->nackImpl($message->deliveryTag, $multiple, $requeue);
    }

    /**
     * Rejects given message.
     */
    public function reject(Message $message, bool $requeue = true): bool
    {
        return $this->rejectImpl($message->deliveryTag, $requeue);
    }

    /**
     * Synchronously returns message if there is any waiting in the queue.
     */
    public function get(string $queue = '', bool $noAck = false): Message|null
    {
        $response = $this->getImpl($queue, $noAck);

        if ($response instanceof MethodBasicGetEmptyFrame) {
            return null;
        }

        if ($response instanceof MethodBasicGetOkFrame) {
            $this->state = ChannelState::AwaitingHeader;

            $headerFrame = $this->connection->awaitContentHeader($this->getChannelId());
            $this->headerFrame = $headerFrame;
            $this->bodySizeRemaining = $headerFrame->bodySize;
            $this->state = ChannelState::AwaitingBody;

            while ($this->bodySizeRemaining > 0) {
                $bodyFrame = $this->connection->awaitContentBody($this->getChannelId());

                $this->bodyBuffer->append($bodyFrame->payload);
                $this->bodySizeRemaining -= $bodyFrame->payloadSize;

                if ($this->bodySizeRemaining < 0) {
                    $this->state = ChannelState::Error;
                    $this->client->disconnect(Constants::STATUS_SYNTAX_ERROR, $errorMessage = 'Body overflow, received ' . (-$this->bodySizeRemaining) . ' more bytes.');

                    throw new ChannelException($errorMessage);
                }
            }

            $this->state = ChannelState::Ready;

            $message = new Message(
                null,
                $response->deliveryTag,
                $response->redelivered,
                $response->exchange,
                $response->routingKey,
                $this->headerFrame->toArray(),
                $this->bodyBuffer->consume($this->bodyBuffer->getLength()),
            );

            $this->headerFrame = null;

            return $message;
        }

        throw new LogicException('This statement should never be reached.');
    }

    /**
     * Published message to given exchange.
     *
     * @param array<string,mixed> $headers
     */
    public function publish(string $body, array $headers = [], string $exchange = '', string $routingKey = '', bool $mandatory = false, bool $immediate = false): bool|int
    {
        $response = $this->publishImpl($body, $headers, $exchange, $routingKey, $mandatory, $immediate);

        if ($this->mode === ChannelMode::Confirm) {
            return ++$this->deliveryTag;
        }

        return $response;
    }

    /**
     * Cancels given consumer subscription.
     */
    public function cancel(string $consumerTag, bool $nowait = false): bool|MethodBasicCancelOkFrame
    {
        $response = $this->cancelImpl($consumerTag, $nowait);
        unset($this->consumeConcurrency[$consumerTag]);
        unset($this->consumeConcurrent[$consumerTag]);
        unset($this->deliverCallbacks[$consumerTag]);
        unset($this->deliveryQueue[$consumerTag]);

        return $response;
    }

    /**
     * Changes channel to transactional mode. All messages are published to queues only after {@link txCommit()} is called.
     */
    public function txSelect(): MethodTxSelectOkFrame
    {
        if ($this->mode !== ChannelMode::Regular) {
            throw new ChannelException('Channel not in regular mode, cannot change to transactional mode.');
        }

        $response = $this->txSelectImpl();
        $this->mode = ChannelMode::Transactional;

        return $response;
    }

    /**
     * Commit transaction.
     */
    public function txCommit(): MethodTxCommitOkFrame
    {
        if ($this->mode !== ChannelMode::Transactional) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.commit'.");
        }

        return $this->txCommitImpl();
    }

    /**
     * Rollback transaction.
     */
    public function txRollback(): MethodTxRollbackOkFrame
    {
        if ($this->mode !== ChannelMode::Transactional) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.rollback'.");
        }

        return $this->txRollbackImpl();
    }

    /**
     * Changes channel to confirm mode. Broker then asynchronously sends 'basic.ack's for published messages.
     */
    public function confirmSelect(?callable $callback = null, bool $nowait = false): bool|MethodConfirmSelectOkFrame
    {
        if ($this->mode !== ChannelMode::Regular) {
            throw new ChannelException('Channel not in regular mode, cannot change to transactional mode.');
        }

        $response = $this->confirmSelectImpl($nowait);
        $this->enterConfirmMode($callback);

        return $response;
    }

    private function enterConfirmMode(?callable $callback = null): void
    {
        $this->mode = ChannelMode::Confirm;
        $this->deliveryTag = 0;

        if ($callback) {
            $this->addAckListener($callback);
        }
    }

    /**
     * Callback after channel-level frame has been received.
     */
    public function onFrameReceived(AbstractFrame $frame): void
    {
        try {
            if ($this->state === ChannelState::Error) {
                throw new ChannelException('Channel in error state.');
            }

            if ($this->state === ChannelState::Closed) {
                throw new ChannelException(sprintf('Received frame #%d on closed channel #%d.', $frame->type, $this->channelId));
            }

            if ($frame instanceof MethodFrame) {
                if ($this->state === ChannelState::Closing && !($frame instanceof MethodChannelCloseOkFrame)) {
                    // drop frames in closing state
                    return;
                }

                if ($this->state !== ChannelState::Ready && !($frame instanceof MethodChannelCloseOkFrame)) {
                    $currentState = $this->state;
                    $this->state = ChannelState::Error;

                    if ($currentState === ChannelState::AwaitingHeader) {
                        $msg = 'Got method frame, expected header frame.';
                    } elseif ($currentState === ChannelState::AwaitingBody) {
                        $msg = 'Got method frame, expected body frame.';
                    } else {
                        throw new LogicException('Unhandled channel state.');
                    }

                    $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                    throw new ChannelException('Unexpected frame: ' . $msg);
                }

                if ($frame instanceof MethodChannelCloseOkFrame) {
                    $this->onClose();
                } elseif ($frame instanceof MethodBasicReturnFrame) {
                    $this->returnFrame = $frame;
                    $this->state = ChannelState::AwaitingHeader;
                } elseif ($frame instanceof MethodBasicDeliverFrame) {
                    $this->deliverFrame = $frame;
                    $this->state = ChannelState::AwaitingHeader;
                } elseif ($frame instanceof MethodBasicAckFrame) {
                    foreach ($this->ackCallbacks as $callback) {
                        $callback($frame);
                    }
                } elseif ($frame instanceof MethodBasicNackFrame) {
                    foreach ($this->ackCallbacks as $callback) {
                        $callback($frame);
                    }
                } elseif ($frame instanceof MethodChannelCloseFrame) {
                    $this->onClose();

                    throw new ChannelException('Channel closed by server: ' . $frame->replyText, $frame->replyCode);
                } else {
                    throw new ChannelException('Unhandled method frame ' . $frame::class . '.');
                }
            } elseif ($frame instanceof ContentHeaderFrame) {
                if ($this->state === ChannelState::Closing) {
                    // drop frames in closing state
                    return;
                }

                if ($this->state !== ChannelState::AwaitingHeader) {
                    $currentState = $this->state;
                    $this->state = ChannelState::Error;

                    if ($currentState === ChannelState::Ready) {
                        $msg = 'Got header frame, expected method frame.';
                    } elseif ($currentState === ChannelState::AwaitingBody) {
                        $msg = 'Got header frame, expected content frame.';
                    } else {
                        throw new LogicException('Unhandled channel state.');
                    }

                    $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                    throw new ChannelException('Unexpected frame: ' . $msg);
                }

                $this->headerFrame = $frame;
                $this->bodySizeRemaining = $frame->bodySize;

                if ($this->bodySizeRemaining > 0) {
                    $this->state = ChannelState::AwaitingBody;
                } else {
                    $this->state = ChannelState::Ready;
                    $this->onBodyComplete();
                }
            } elseif ($frame instanceof ContentBodyFrame) {
                if ($this->state === ChannelState::Closing) {
                    // drop frames in closing state
                    return;
                }

                if ($this->state !== ChannelState::AwaitingBody) {
                    $currentState = $this->state;
                    $this->state = ChannelState::Error;

                    if ($currentState === ChannelState::Ready) {
                        $msg = 'Got body frame, expected method frame.';
                    } elseif ($currentState === ChannelState::AwaitingHeader) {
                        $msg = 'Got body frame, expected header frame.';
                    } else {
                        throw new LogicException('Unhandled channel state.');
                    }

                    $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                    throw new ChannelException('Unexpected frame: ' . $msg);
                }

                $this->bodyBuffer->append($frame->payload);
                $this->bodySizeRemaining -= $frame->payloadSize;

                if ($this->bodySizeRemaining < 0) {
                    $this->state = ChannelState::Error;
                    $this->client->disconnect(Constants::STATUS_SYNTAX_ERROR, 'Body overflow, received ' . (-$this->bodySizeRemaining) . ' more bytes.');
                } elseif ($this->bodySizeRemaining === 0) {
                    $this->state = ChannelState::Ready;
                    $this->onBodyComplete();
                }
            } elseif ($frame instanceof HeartbeatFrame) {
                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, 'Got heartbeat on non-zero channel.');

                throw new ChannelException('Unexpected heartbeat frame.');
            } else {
                throw new ChannelException('Unhandled frame ' . $frame::class . '.');
            }
        } catch (Throwable $e) {
            $this->emit('error', [$e]);
        }
    }

    /**
     * Callback after channel has been closed.
     */
    private function onClose(): void
    {
        $this->state = ChannelState::Closed;

        $this->emit('close');

        if ($this->closeDeferred !== null) {
            $this->closeDeferred->resolve($this->channelId);
        }

        // break reference cycle, must be called after resolving promise
        // $this->client = null;
        // break consumers' reference cycle
        $this->consumeConcurrency = [];
        $this->consumeConcurrent = [];
        $this->deliverCallbacks = [];
        $this->deliveryQueue = [];
    }

    /**
     * Callback after content body has been completely received.
     */
    private function onBodyComplete(): void
    {
        if ($this->returnFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());
            $message = new Message(
                null,
                null,
                false,
                $this->returnFrame->exchange,
                $this->returnFrame->routingKey,
                $this->headerFrame->toArray(),
                $content,
            );

            foreach ($this->returnCallbacks as $callback) {
                async(fn () => $callback($message, $this->returnFrame))();
            }

            $this->returnFrame = null;
            $this->headerFrame = null;
        } elseif ($this->deliverFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());
            if (isset($this->deliverCallbacks[$this->deliverFrame->consumerTag])) {
                $message = new Message(
                    $this->deliverFrame->consumerTag,
                    $this->deliverFrame->deliveryTag,
                    $this->deliverFrame->redelivered,
                    $this->deliverFrame->exchange,
                    $this->deliverFrame->routingKey,
                    $this->headerFrame->toArray(),
                    $content,
                );

                $this->deliveryQueue[$this->deliverFrame->consumerTag]->enqueue($message);
                $this->deliveryTick($this->deliverFrame->consumerTag);
            }

            $this->deliverFrame = null;
            $this->headerFrame = null;
        } else {
            throw new LogicException('Either return or deliver frame has to be handled here.');
        }
    }

    private function deliveryTick(string $consumerTag): void
    {
        if (
            (
                !array_key_exists($consumerTag, $this->consumeConcurrent)
            ) ||
            (
                array_key_exists($consumerTag, $this->consumeConcurrent) && $this->consumeConcurrent[$consumerTag] >= $this->consumeConcurrency[$consumerTag]
            ) ||
            (
                empty($this->deliveryQueue[$consumerTag])
            ) ||
            (
                $this->deliveryQueue[$consumerTag]->isEmpty()
            )
        ) {
            return;
        }

        $this->consumeConcurrent[$consumerTag]++;
        $message = $this->deliveryQueue[$consumerTag]->dequeue();
        $callback = $this->deliverCallbacks[$consumerTag];

        $outcome = $callback($message, $this, $this->client);

        /**
         * Handle channel close getting called while handling a message.
         * Also not handling more messages when the channel is closing or when it errors.
         * Those put the channel in a state we can't consume any more messages.
         */
        if ($this->state === ChannelState::Closing || $this->state === ChannelState::Closed || $this->state === ChannelState::Error) {
            return;
        }

        if (!($outcome instanceof PromiseInterface)) {
            $this->consumeConcurrent[$consumerTag]--;
            $this->deliveryTick($consumerTag);

            return;
        }

        $outcome->finally(function () use ($consumerTag): void {
            if (array_key_exists($consumerTag, $this->consumeConcurrent)) {
                $this->consumeConcurrent[$consumerTag]--;
            }

            $this->deliveryTick($consumerTag);
        });
    }
}
