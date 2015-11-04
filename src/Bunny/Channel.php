<?php
namespace Bunny;

use Bunny\Exception\ChannelException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicDeliverFrame;
use Bunny\Protocol\MethodBasicGetEmptyFrame;
use Bunny\Protocol\MethodBasicGetOkFrame;
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
        ChannelMethods::consume as basicConsume;
        ChannelMethods::ack as basicAck;
        ChannelMethods::reject as basicReject;
        ChannelMethods::nack as basicNack;
        ChannelMethods::get as basicGet;
        ChannelMethods::cancel as basicCancel;
    }

    /** @var AbstractClient */
    protected $client;

    /** @var int */
    protected $channelId;

    /** @var callable[] */
    protected $deliverCallbacks = [];

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

    /** @var Deferred */
    protected $closeDeferred;

    /** @var PromiseInterface */
    protected $closePromise;

    /** @var Deferred */
    protected $getDeferred;

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
        $response = $this->basicConsume($queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);

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
            $response->then(function () {
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
        return $this->basicAck($message->deliveryTag, $multiple);
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
        return $this->basicNack($message->deliveryTag, $multiple, $requeue);
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
        return $this->basicReject($message->deliveryTag, $reqeue);
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

        $response = $this->basicGet($queue, $noAck);

        if ($response instanceof PromiseInterface) {
            $this->getDeferred = new Deferred();

            $response->then(function ($frame) {
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

                } elseif ($this->bodySizeRemaining === 0) {
                    $this->state = ChannelStateEnum::READY;
                }
            }

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
     * Cancels given consumer subscription.
     *
     * @param string $consumerTag
     * @param bool $nowait
     * @return bool|Protocol\MethodBasicCancelOkFrame|PromiseInterface
     */
    public function cancel($consumerTag, $nowait = false)
    {
        $response = $this->basicCancel($consumerTag, $nowait);
        unset($this->deliverCallbacks[$consumerTag]);
        return $response;
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
            $this->state = ChannelStateEnum::AWAITING_BODY;

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
            // TODO

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
