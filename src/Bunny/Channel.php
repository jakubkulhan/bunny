<?php
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
use Bunny\Protocol\MethodChannelCloseOkFrame;
use Bunny\Protocol\MethodFrame;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * AMQP channel.
 *
 * - Closely works with underlying client instance.
 * - Manages consumers.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Channel
{

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

    /** @var AbstractClient */
    protected $client;

    /** @var int */
    protected $channelId;

    /** @var callable[] */
    protected $returnCallbacks = [];

    /** @var callable[] */
    protected $deliverCallbacks = [];

    /** @var callable[] */
    protected $ackCallbacks = [];

    /** @var MethodBasicReturnFrame */
    protected $returnFrame;

    /** @var MethodBasicDeliverFrame */
    protected $deliverFrame;

    /** @var MethodBasicGetOkFrame */
    protected $getOkFrame;

    /** @var ContentHeaderFrame */
    protected $headerFrame;

    /** @var int */
    protected $bodySizeRemaining;

    /** @var Buffer */
    protected $bodyBuffer;

    /** @var int */
    protected $state = ChannelStateEnum::READY;

    /** @var int */
    protected $mode = ChannelModeEnum::REGULAR;

    /** @var Deferred */
    protected $closeDeferred;

    /** @var PromiseInterface */
    protected $closePromise;

    /** @var Deferred */
    protected $getDeferred;

    /** @var int */
    protected $deliveryTag;

    /**
     * Constructor.
     *
     * @param AbstractClient $client
     * @param int $channelId
     */
    public function __construct(AbstractClient $client, $channelId)
    {
        $this->client = $client;
        $this->channelId = $channelId;
        $this->bodyBuffer = new Buffer();
    }

    /**
     * Returns underlying client instance.
     *
     * @return AbstractClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Returns channel id.
     *
     * @return int
     */
    public function getChannelId()
    {
        return $this->channelId;
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
        if ($this->mode !== ChannelModeEnum::CONFIRM) {
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
        if ($this->mode !== ChannelModeEnum::CONFIRM) {
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
     *
     * @param int $replyCode
     * @param string $replyText
     * @return PromiseInterface
     */
    public function close($replyCode = 0, $replyText = "")
    {
        if ($this->state === ChannelStateEnum::CLOSED) {
            throw new ChannelException("Trying to close already closed channel #{$this->channelId}.");
        }

        if ($this->state === ChannelStateEnum::CLOSING) {
            return $this->closePromise;
        }

        $this->state = ChannelStateEnum::CLOSING;

        $this->client->channelClose($this->channelId, $replyCode, $replyText, 0, 0);
        $this->closeDeferred = new Deferred();
        return $this->closePromise = $this->closeDeferred->promise()->then(function () {
            $this->client->removeChannel($this->channelId);
        });
    }

    /**
     * Creates new consumer on channel.
     *
     * @param callable $callback
     * @param string $queue
     * @param string $consumerTag
     * @param bool $noLocal
     * @param bool $noAck
     * @param bool $exclusive
     * @param bool $nowait
     * @param array $arguments
     * @return MethodBasicConsumeOkFrame|PromiseInterface
     */
    public function consume(callable $callback, $queue = "", $consumerTag = "", $noLocal = false, $noAck = false, $exclusive = false, $nowait = false, $arguments = [])
    {
        $response = $this->consumeImpl($queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);

        if ($response instanceof MethodBasicConsumeOkFrame) {
            $this->deliverCallbacks[$response->consumerTag] = $callback;
            return $response;

        } elseif ($response instanceof PromiseInterface) {
            return $response->then(function (MethodBasicConsumeOkFrame $response) use ($callback) {
                $this->deliverCallbacks[$response->consumerTag] = $callback;
                return $response;
            });

        } else {
            throw new ChannelException(
                "basic.consume unexpected response of type " . gettype($response) .
                (is_object($response) ? " (" . get_class($response) . ")" : "") .
                "."
            );
        }
    }

    /**
     * Convenience method that registers consumer and then starts client event loop.
     *
     * @param callable $callback
     * @param string $queue
     * @param string $consumerTag
     * @param bool $noLocal
     * @param bool $noAck
     * @param bool $exclusive
     * @param bool $nowait
     * @param array $arguments
     */
    public function run(callable $callback, $queue = "", $consumerTag = "", $noLocal = false, $noAck = false, $exclusive = false, $nowait = false, $arguments = [])
    {
        $response = $this->consume($callback, $queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);

        if ($response instanceof MethodBasicConsumeOkFrame) {
            $this->client->run();

        } elseif ($response instanceof PromiseInterface) {
            $response->done(function () {
                $this->client->run();
            });

        } else {
            throw new ChannelException(
                "Unexpected response of type " . gettype($response) .
                (is_object($response) ? " (" . get_class($response) . ")" : "") .
                "."
            );
        }
    }

    /**
     * Acks given message.
     *
     * @param Message $message
     * @param boolean $multiple
     * @return boolean|PromiseInterface
     */
    public function ack(Message $message, $multiple = false)
    {
        return $this->ackImpl($message->deliveryTag, $multiple);
    }

    /**
     * Nacks given message.
     *
     * @param Message $message
     * @param boolean $multiple
     * @param boolean $requeue
     * @return boolean|PromiseInterface
     */
    public function nack(Message $message, $multiple = false, $requeue = true)
    {
        return $this->nackImpl($message->deliveryTag, $multiple, $requeue);
    }

    /**
     * Rejects given message.
     *
     * @param Message $message
     * @param bool $reqeue
     * @return boolean|PromiseInterface
     */
    public function reject(Message $message, $reqeue = true)
    {
        return $this->rejectImpl($message->deliveryTag, $reqeue);
    }

    /**
     * Synchronously returns message if there is any waiting in the queue.
     *
     * @param string $queue
     * @param bool $noAck
     * @return Message|PromiseInterface
     */
    public function get($queue = "", $noAck = false)
    {
        if ($this->getDeferred !== null) {
            throw new ChannelException("Another 'basic.get' already in progress. You should use 'basic.consume' instead of multiple 'basic.get'.");
        }

        $response = $this->getImpl($queue, $noAck);

        if ($response instanceof PromiseInterface) {
            $this->getDeferred = new Deferred();

            $response->done(function ($frame) {
                if ($frame instanceof MethodBasicGetEmptyFrame) {
                    // deferred has to be first nullified and then resolved, otherwise results in race condition
                    $deferred = $this->getDeferred;
                    $this->getDeferred = null;
                    $deferred->resolve(null);

                } elseif ($frame instanceof MethodBasicGetOkFrame) {
                    $this->getOkFrame = $frame;
                    $this->state = ChannelStateEnum::AWAITING_HEADER;

                } else {
                    throw new \LogicException("This statement should never be reached.");
                }
            });

            return $this->getDeferred->promise();

        } elseif ($response instanceof MethodBasicGetEmptyFrame) {
            return null;

        } elseif ($response instanceof MethodBasicGetOkFrame) {
            $this->state = ChannelStateEnum::AWAITING_HEADER;

            $headerFrame = $this->getClient()->awaitContentHeader($this->getChannelId());
            $this->headerFrame = $headerFrame;
            $this->bodySizeRemaining = $headerFrame->bodySize;
            $this->state = ChannelStateEnum::AWAITING_BODY;

            while ($this->bodySizeRemaining > 0) {
                $bodyFrame = $this->getClient()->awaitContentBody($this->getChannelId());

                $this->bodyBuffer->append($bodyFrame->payload);
                $this->bodySizeRemaining -= $bodyFrame->payloadSize;

                if ($this->bodySizeRemaining < 0) {
                    $this->state = ChannelStateEnum::ERROR;
                    $this->client->disconnect(Constants::STATUS_SYNTAX_ERROR, $errorMessage = "Body overflow, received " . (-$this->bodySizeRemaining) . " more bytes.");
                    throw new ChannelException($errorMessage);
                }
            }

            $this->state = ChannelStateEnum::READY;

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
     *
     * @param string $body
     * @param array $headers
     * @param string $exchange
     * @param string $routingKey
     * @param bool $mandatory
     * @param bool $immediate
     * @return bool|PromiseInterface|int
     */
    public function publish($body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false)
    {
        $response = $this->publishImpl($body, $headers, $exchange, $routingKey, $mandatory, $immediate);

        if ($this->mode === ChannelModeEnum::CONFIRM) {
            if ($response instanceof PromiseInterface) {
                return $response->then(function () {
                    return ++$this->deliveryTag;
                });
            } else {
                return ++$this->deliveryTag;
            }
        } else {
            return $response;
        }
    }

    /**
     * Cancels given consumer subscription.
     *
     * @param string $consumerTag
     * @param bool $nowait
     * @return bool|Protocol\MethodBasicCancelOkFrame|PromiseInterface
     */
    public function cancel($consumerTag, $nowait = false)
    {
        $response = $this->cancelImpl($consumerTag, $nowait);
        unset($this->deliverCallbacks[$consumerTag]);
        return $response;
    }

    /**
     * Changes channel to transactional mode. All messages are published to queues only after {@link txCommit()} is called.
     *
     * @return Protocol\MethodTxSelectOkFrame|PromiseInterface
     */
    public function txSelect()
    {
        if ($this->mode !== ChannelModeEnum::REGULAR) {
            throw new ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->txSelectImpl();

        if ($response instanceof PromiseInterface) {
            return $response->then(function ($response) {
                $this->mode = ChannelModeEnum::TRANSACTIONAL;
                return $response;
            });

        } else {
            $this->mode = ChannelModeEnum::TRANSACTIONAL;
            return $response;
        }
    }

    /**
     * Commit transaction.
     *
     * @return Protocol\MethodTxCommitOkFrame|PromiseInterface
     */
    public function txCommit()
    {
        if ($this->mode !== ChannelModeEnum::TRANSACTIONAL) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.commit'.");
        }

        return $this->txCommitImpl();
    }

    /**
     * Rollback transaction.
     *
     * @return Protocol\MethodTxRollbackOkFrame|PromiseInterface
     */
    public function txRollback()
    {
        if ($this->mode !== ChannelModeEnum::TRANSACTIONAL) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.rollback'.");
        }

        return $this->txRollbackImpl();
    }

    /**
     * Changes channel to confirm mode. Broker then asynchronously sends 'basic.ack's for published messages.
     *
     * @param bool $nowait
     * @return Protocol\MethodConfirmSelectOkFrame|PromiseInterface
     */
    public function confirmSelect(callable $callback = null, $nowait = false)
    {
        if ($this->mode !== ChannelModeEnum::REGULAR) {
            throw new ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->confirmSelectImpl($nowait);

        if ($response instanceof PromiseInterface) {
            return $response->then(function ($response) use ($callback) {
                $this->enterConfirmMode($callback);
                return $response;
            });

        } else {
            $this->enterConfirmMode($callback);
            return $response;
        }
    }

    private function enterConfirmMode(callable $callback = null)
    {
        $this->mode = ChannelModeEnum::CONFIRM;
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
    public function onFrameReceived(AbstractFrame $frame)
    {
        if ($this->state === ChannelStateEnum::ERROR) {
            throw new ChannelException("Channel in error state.");
        }

        if ($this->state === ChannelStateEnum::CLOSED) {
            throw new ChannelException("Received frame #{$frame->type} on closed channel #{$this->channelId}.");
        }

        if ($frame instanceof MethodFrame) {
            if ($this->state === ChannelStateEnum::CLOSING && !($frame instanceof MethodChannelCloseOkFrame)) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== ChannelStateEnum::READY && !($frame instanceof MethodChannelCloseOkFrame)) {
                $currentState = $this->state;
                $this->state = ChannelStateEnum::ERROR;

                if ($currentState === ChannelStateEnum::AWAITING_HEADER) {
                    $msg = "Got method frame, expected header frame.";
                } elseif ($currentState === ChannelStateEnum::AWAITING_BODY) {
                    $msg = "Got method frame, expected body frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
            }

            if ($frame instanceof MethodChannelCloseOkFrame) {
                $this->state = ChannelStateEnum::CLOSED;

                if ($this->closeDeferred !== null) {
                    $this->closeDeferred->resolve($this->channelId);
                }

                // break reference cycle, must be called after resolving promise
                unset($this->client);
                // break consumers' reference cycle
                $this->deliverCallbacks = [];

            } elseif ($frame instanceof MethodBasicReturnFrame) {
                $this->returnFrame = $frame;
                $this->state = ChannelStateEnum::AWAITING_HEADER;

            } elseif ($frame instanceof MethodBasicDeliverFrame) {
                $this->deliverFrame = $frame;
                $this->state = ChannelStateEnum::AWAITING_HEADER;

            } elseif ($frame instanceof MethodBasicAckFrame) {
                foreach ($this->ackCallbacks as $callback) {
                    $callback($frame);
                }

            } elseif ($frame instanceof MethodBasicNackFrame) {
                foreach ($this->ackCallbacks as $callback) {
                    $callback($frame);
                }

            } else {
                throw new ChannelException("Unhandled method frame " . get_class($frame) . ".");
            }

        } elseif ($frame instanceof ContentHeaderFrame) {
            if ($this->state === ChannelStateEnum::CLOSING) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== ChannelStateEnum::AWAITING_HEADER) {
                $currentState = $this->state;
                $this->state = ChannelStateEnum::ERROR;

                if ($currentState === ChannelStateEnum::READY) {
                    $msg = "Got header frame, expected method frame.";
                } elseif ($currentState === ChannelStateEnum::AWAITING_BODY) {
                    $msg = "Got header frame, expected content frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
            }

            $this->headerFrame = $frame;
            $this->bodySizeRemaining = $frame->bodySize;

            if ($this->bodySizeRemaining > 0) {
                $this->state = ChannelStateEnum::AWAITING_BODY;
            } else {
                $this->state = ChannelStateEnum::READY;
                $this->onBodyComplete();
            }

        } elseif ($frame instanceof ContentBodyFrame) {
            if ($this->state === ChannelStateEnum::CLOSING) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== ChannelStateEnum::AWAITING_BODY) {
                $currentState = $this->state;
                $this->state = ChannelStateEnum::ERROR;

                if ($currentState === ChannelStateEnum::READY) {
                    $msg = "Got body frame, expected method frame.";
                } elseif ($currentState === ChannelStateEnum::AWAITING_HEADER) {
                    $msg = "Got body frame, expected header frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
            }

            $this->bodyBuffer->append($frame->payload);
            $this->bodySizeRemaining -= $frame->payloadSize;

            if ($this->bodySizeRemaining < 0) {
                $this->state = ChannelStateEnum::ERROR;
                $this->client->disconnect(Constants::STATUS_SYNTAX_ERROR, "Body overflow, received " . (-$this->bodySizeRemaining) . " more bytes.");

            } elseif ($this->bodySizeRemaining === 0) {
                $this->state = ChannelStateEnum::READY;
                $this->onBodyComplete();
            }

        } elseif ($frame instanceof HeartbeatFrame) {
            $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got heartbeat on non-zero channel.");
            throw new ChannelException("Unexpected heartbeat frame.");

        } else {
            throw new ChannelException("Unhandled frame " . get_class($frame) . ".");
        }
    }

    /**
     * Callback after content body has been completely received.
     */
    protected function onBodyComplete()
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
                $callback($message, $this->returnFrame);
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
