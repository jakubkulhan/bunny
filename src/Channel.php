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
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicDeliverFrame;
use Bunny\Protocol\MethodBasicGetEmptyFrame;
use Bunny\Protocol\MethodBasicGetOkFrame;
use Bunny\Protocol\MethodBasicNackFrame;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Protocol\MethodChannelCloseFrame;
use Bunny\Protocol\MethodChannelCloseOkFrame;
use Bunny\Protocol\MethodFrame;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Async\async;
use function React\Async\await;

/**
 * AMQP channel.
 *
 * - Closely works with underlying client instance.
 * - Manages consumers.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 * @final Will be marked final in a future major release
 */
class Channel implements ChannelInterface, EventEmitterInterface
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

    /** @var callable[] */
    private $returnCallbacks = [];

    /** @var callable[] */
    private $deliverCallbacks = [];

    /** @var callable[] */
    private $ackCallbacks = [];

    /** @var ?MethodBasicReturnFrame */
    private $returnFrame;

    /** @var ?MethodBasicDeliverFrame */
    private $deliverFrame;

    /** @var ?MethodBasicGetOkFrame */
    private $getOkFrame;

    /** @var ?ContentHeaderFrame */
    private $headerFrame;

    /** @var int */
    private $bodySizeRemaining;

    /** @var Buffer */
    private $bodyBuffer;

    private ChannelState $state = ChannelState::Ready;

    private ChannelMode $mode = ChannelMode::Regular;

    /** @var Deferred */
    private $closeDeferred;

    /** @var PromiseInterface */
    private $closePromise;

    /** @var ?Deferred */
    private $getDeferred;

    /** @var int */
    private $deliveryTag;

    public function __construct(private Connection $connection, private Client $client, readonly public int $channelId)
    {
        $this->bodyBuffer = new Buffer();
    }

    public function getClient(): Connection
    {
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
     * @param callable $callback
     * @return $this
     */
    public function addReturnListener(callable $callback)
    {
        $this->removeReturnListener($callback); // remove if previously added to prevent calling multiple times
        $this->returnCallbacks[] = $callback;
        return $this;
    }

    /**
     * Removes registered return listener. If the callback is not registered, this is noop.
     *
     * @param callable $callback
     * @return $this
     */
    public function removeReturnListener(callable $callback)
    {
        foreach ($this->returnCallbacks as $k => $v) {
            if ($v === $callback) {
                unset($this->returnCallbacks[$k]);
            }
        }

        return $this;
    }

    /**
     * Listener is called whenever 'basic.ack' or 'basic.nack' is received.
     *
     * @param callable $callback
     * @return $this
     */
    public function addAckListener(callable $callback)
    {
        if ($this->mode !== ChannelMode::Confirm) {
            throw new ChannelException("Ack/nack listener can be added when channel in confirm mode.");
        }

        $this->removeAckListener($callback);
        $this->ackCallbacks[] = $callback;
        return $this;
    }

    /**
     * Removes registered ack/nack listener. If the callback is not registered, this is noop.
     *
     * @param callable $callback
     * @return $this
     */
    public function removeAckListener(callable $callback)
    {
        if ($this->mode !== ChannelMode::Confirm) {
            throw new ChannelException("Ack/nack listener can be removed when channel in confirm mode.");
        }

        foreach ($this->ackCallbacks as $k => $v) {
            if ($v === $callback) {
                unset($this->ackCallbacks[$k]);
            }
        }

        return $this;
    }

    /**
     * Closes channel.
     *
     * Always returns a promise, because there can be outstanding messages to be processed.
     */
    public function close(int $replyCode = 0, string $replyText = ""): void
    {
        if ($this->state === ChannelState::Closed) {
            throw new ChannelException("Trying to close already closed channel #{$this->channelId}.");
        }

        if ($this->state === ChannelState::Closing) {
            await($this->closePromise);
            return;
        }

        $this->state = ChannelState::Closing;

        $this->connection->channelClose($this->channelId, $replyCode, 0, 0, $replyText);
        $this->closeDeferred = new Deferred();
        $this->closePromise = $this->closeDeferred->promise()->then(function () {
            $this->emit('close');
        });

        await($this->closePromise);
    }

    /**
     * Creates new consumer on channel.
     */
    public function consume(callable $callback, string $queue = "", string $consumerTag = "", bool $noLocal = false, bool $noAck = false, bool $exclusive = false, bool $nowait = false, array $arguments = []): MethodBasicConsumeOkFrame
    {
        $response = $this->consumeImpl($queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);

        if ($response instanceof MethodBasicConsumeOkFrame) {
            $this->deliverCallbacks[$response->consumerTag] = $callback;
            return $response;

        }

        throw new ChannelException(
            "basic.consume unexpected response of type " . gettype($response) . "."
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
    public function get(string $queue = "", bool $noAck = false): Message|null
    {
        if ($this->getDeferred !== null) {
            throw new ChannelException("Another 'basic.get' already in progress. You should use 'basic.consume' instead of multiple 'basic.get'.");
        }

        $response = $this->getImpl($queue, $noAck);

        if ($response instanceof MethodBasicGetEmptyFrame) {
            return null;

        } elseif ($response instanceof MethodBasicGetOkFrame) {
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
                    $this->connection->disconnect(Constants::STATUS_SYNTAX_ERROR, $errorMessage = "Body overflow, received " . (-$this->bodySizeRemaining) . " more bytes.");
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
                $this->bodyBuffer->consume($this->bodyBuffer->getLength())
            );

            $this->headerFrame = null;

            return $message;

        } else {
            throw new \LogicException("This statement should never be reached.");
        }
    }

    /**
     * Published message to given exchange.
     */
    public function publish($body, array $headers = [], string $exchange = '', string $routingKey = '', bool $mandatory = false, bool $immediate = false): bool|int
    {
        $response = $this->publishImpl($body, $headers, $exchange, $routingKey, $mandatory, $immediate);

        if ($this->mode === ChannelMode::Confirm) {
            return ++$this->deliveryTag;
        } else {
            return $response;
        }
    }

    /**
     * Cancels given consumer subscription.
     */
    public function cancel(string $consumerTag, bool $nowait = false): bool|\Bunny\Protocol\MethodBasicCancelOkFrame
    {
        $response = $this->cancelImpl($consumerTag, $nowait);
        unset($this->deliverCallbacks[$consumerTag]);
        return $response;
    }

    /**
     * Changes channel to transactional mode. All messages are published to queues only after {@link txCommit()} is called.
     */
    public function txSelect(): \Bunny\Protocol\MethodTxSelectOkFrame
    {
        if ($this->mode !== ChannelMode::Regular) {
            throw new ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->txSelectImpl();
        $this->mode = ChannelMode::Transactional;

        return $response;
    }

    /**
     * Commit transaction.
     */
    public function txCommit(): \Bunny\Protocol\MethodTxCommitOkFrame
    {
        if ($this->mode !== ChannelMode::Transactional) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.commit'.");
        }

        return $this->txCommitImpl();
    }

    /**
     * Rollback transaction.
     */
    public function txRollback(): \Bunny\Protocol\MethodTxRollbackOkFrame
    {
        if ($this->mode !== ChannelMode::Transactional) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.rollback'.");
        }

        return $this->txRollbackImpl();
    }

    /**
     * Changes channel to confirm mode. Broker then asynchronously sends 'basic.ack's for published messages.
     */
    public function confirmSelect(callable $callback = null, bool $nowait = false): \Bunny\Protocol\MethodConfirmSelectOkFrame
    {
        if ($this->mode !== ChannelMode::Regular) {
            throw new ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->confirmSelectImpl($nowait);
        $this->enterConfirmMode($callback);

        return $response;
    }

    private function enterConfirmMode(callable $callback = null): void
    {
        $this->mode = ChannelMode::Confirm;
        $this->deliveryTag = 0;

        if ($callback) {
            $this->addAckListener($callback);
        }
    }

    /**
     * Callback after channel-level frame has been received.
     *
     * @param AbstractFrame $frame
     */
    public function onFrameReceived(AbstractFrame $frame): void
    {
        if ($this->state === ChannelState::Error) {
            throw new ChannelException("Channel in error state.");
        }

        if ($this->state === ChannelState::Closed) {
            throw new ChannelException("Received frame #{$frame->type} on closed channel #{$this->channelId}.");
        }

        if ($frame instanceof MethodFrame) {
            if ($this->state === ChannelState::Closing && !($frame instanceof MethodChannelCloseOkFrame)) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== ChannelState::Ready && !($frame instanceof MethodChannelCloseOkFrame)) {
                $currentState = $this->state;
                $this->state = ChannelState::Error;

                if ($currentState === ChannelState::AwaitingHeader) {
                    $msg = "Got method frame, expected header frame.";
                } elseif ($currentState === ChannelState::AwaitingBody) {
                    $msg = "Got method frame, expected body frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->connection->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
            }

            if ($frame instanceof MethodChannelCloseOkFrame) {
                $this->state = ChannelState::Closed;

                if ($this->closeDeferred !== null) {
                    $this->closeDeferred->resolve($this->channelId);
                }

//                // break reference cycle, must be called after resolving promise
//                $this->client = null;
                // break consumers' reference cycle
                $this->deliverCallbacks = [];

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
                throw new ChannelException("Channel closed by server: " . $frame->replyText, $frame->replyCode);

            } else {
                throw new ChannelException("Unhandled method frame " . get_class($frame) . ".");
            }

        } elseif ($frame instanceof ContentHeaderFrame) {
            if ($this->state === ChannelState::Closing) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== ChannelState::AwaitingHeader) {
                $currentState = $this->state;
                $this->state = ChannelState::Error;

                if ($currentState === ChannelState::Ready) {
                    $msg = "Got header frame, expected method frame.";
                } elseif ($currentState === ChannelState::AwaitingBody) {
                    $msg = "Got header frame, expected content frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->connection->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
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

            } elseif ($this->state !== ChannelState::AwaitingBody) {
                $currentState = $this->state;
                $this->state = ChannelState::Error;

                if ($currentState === ChannelState::Ready) {
                    $msg = "Got body frame, expected method frame.";
                } elseif ($currentState === ChannelState::AwaitingHeader) {
                    $msg = "Got body frame, expected header frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->connection->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
            }

            $this->bodyBuffer->append($frame->payload);
            $this->bodySizeRemaining -= $frame->payloadSize;

            if ($this->bodySizeRemaining < 0) {
                $this->state = ChannelState::Error;
                $this->connection->disconnect(Constants::STATUS_SYNTAX_ERROR, "Body overflow, received " . (-$this->bodySizeRemaining) . " more bytes.");

            } elseif ($this->bodySizeRemaining === 0) {
                $this->state = ChannelState::Ready;
                $this->onBodyComplete();
            }

        } elseif ($frame instanceof HeartbeatFrame) {
            $this->connection->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got heartbeat on non-zero channel.");
            throw new ChannelException("Unexpected heartbeat frame.");

        } else {
            throw new ChannelException("Unhandled frame " . get_class($frame) . ".");
        }
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
                $content
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
                    $content
                );

                $callback = $this->deliverCallbacks[$this->deliverFrame->consumerTag];

                $callback($message, $this, $this->client);
            }

            $this->deliverFrame = null;
            $this->headerFrame = null;

        } elseif ($this->getOkFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());

            // deferred has to be first nullified and then resolved, otherwise results in race condition
            $deferred = $this->getDeferred;
            $this->getDeferred = null;
            $deferred->resolve(new Message(
                null,
                $this->getOkFrame->deliveryTag,
                $this->getOkFrame->redelivered,
                $this->getOkFrame->exchange,
                $this->getOkFrame->routingKey,
                $this->headerFrame->toArray(),
                $content
            ));

            $this->getOkFrame = null;
            $this->headerFrame = null;

        } else {
            throw new \LogicException("Either return or deliver frame has to be handled here.");
        }
    }
}

