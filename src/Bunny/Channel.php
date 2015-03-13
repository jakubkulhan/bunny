<?php
namespace Bunny;

use Bunny\Exception\ChannelException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\MethodFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicDeliverFrame;
use Bunny\Protocol\MethodBasicReturnFrame;
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

    /** @var ContentHeaderFrame */
    protected $headerFrame;

    /** @var int */
    protected $bodySizeRemaining;

    /** @var Buffer */
    protected $bodyBuffer;

    /** @var int */
    protected $state = ChannelStateEnum::READY;

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
     * @param int $replyCode
     * @param string $replyText
     * @return Protocol\MethodChannelCloseOkFrame|PromiseInterface
     */
    public function close($replyCode = 0, $replyText = "")
    {
        $ret = $this->client->closeChannel($this, $replyCode, $replyText);
        // break reference cycle, must be called AFTER Client::closeChannel(), because in Client::closeChannel() channel's client is checked
        unset($this->client);
        $this->deliverCallbacks = []; // break consumers' reference cycle
        return $ret;
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
     * Callback after channel-level frame has been received.
     *
     * @param AbstractFrame $frame
     */
    public function onFrameReceived(AbstractFrame $frame)
    {
        if ($this->state === ChannelStateEnum::ERROR) {
            throw new ChannelException("Channel in error state.");
        }

        if ($frame instanceof MethodFrame) {
            if ($this->state !== ChannelStateEnum::READY) {
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

            if (false) {
//            } elseif ($frame instanceof MethodConnectionStartFrame) {
//            } elseif ($frame instanceof MethodConnectionStartOkFrame) {
//            } elseif ($frame instanceof MethodConnectionSecureFrame) {
//            } elseif ($frame instanceof MethodConnectionSecureOkFrame) {
//            } elseif ($frame instanceof MethodConnectionTuneFrame) {
//            } elseif ($frame instanceof MethodConnectionTuneOkFrame) {
//            } elseif ($frame instanceof MethodConnectionOpenFrame) {
//            } elseif ($frame instanceof MethodConnectionOpenOkFrame) {
//            } elseif ($frame instanceof MethodConnectionCloseFrame) {
//            } elseif ($frame instanceof MethodConnectionCloseOkFrame) {
//            } elseif ($frame instanceof MethodConnectionBlockedFrame) {
//            } elseif ($frame instanceof MethodConnectionUnblockedFrame) {
//            } elseif ($frame instanceof MethodChannelOpenFrame) {
//            } elseif ($frame instanceof MethodChannelOpenOkFrame) {
//            } elseif ($frame instanceof MethodChannelFlowFrame) {
//            } elseif ($frame instanceof MethodChannelFlowOkFrame) {
//            } elseif ($frame instanceof MethodChannelCloseFrame) {
//            } elseif ($frame instanceof MethodChannelCloseOkFrame) {
//            } elseif ($frame instanceof MethodAccessRequestFrame) {
//            } elseif ($frame instanceof MethodAccessRequestOkFrame) {
//            } elseif ($frame instanceof MethodExchangeDeclareFrame) {
//            } elseif ($frame instanceof MethodExchangeDeclareOkFrame) {
//            } elseif ($frame instanceof MethodExchangeDeleteFrame) {
//            } elseif ($frame instanceof MethodExchangeDeleteOkFrame) {
//            } elseif ($frame instanceof MethodExchangeBindFrame) {
//            } elseif ($frame instanceof MethodExchangeBindOkFrame) {
//            } elseif ($frame instanceof MethodExchangeUnbindFrame) {
//            } elseif ($frame instanceof MethodExchangeUnbindOkFrame) {
//            } elseif ($frame instanceof MethodQueueDeclareFrame) {
//            } elseif ($frame instanceof MethodQueueDeclareOkFrame) {
//            } elseif ($frame instanceof MethodQueueBindFrame) {
//            } elseif ($frame instanceof MethodQueueBindOkFrame) {
//            } elseif ($frame instanceof MethodQueuePurgeFrame) {
//            } elseif ($frame instanceof MethodQueuePurgeOkFrame) {
//            } elseif ($frame instanceof MethodQueueDeleteFrame) {
//            } elseif ($frame instanceof MethodQueueDeleteOkFrame) {
//            } elseif ($frame instanceof MethodQueueUnbindFrame) {
//            } elseif ($frame instanceof MethodQueueUnbindOkFrame) {
//            } elseif ($frame instanceof MethodBasicQosFrame) {
//            } elseif ($frame instanceof MethodBasicQosOkFrame) {
//            } elseif ($frame instanceof MethodBasicConsumeFrame) {
//            } elseif ($frame instanceof MethodBasicConsumeOkFrame) {
//            } elseif ($frame instanceof MethodBasicCancelFrame) {
//            } elseif ($frame instanceof MethodBasicCancelOkFrame) {
//            } elseif ($frame instanceof MethodBasicPublishFrame) {
            } elseif ($frame instanceof MethodBasicReturnFrame) {
                $this->returnFrame = $frame;
                $this->state = ChannelStateEnum::AWAITING_HEADER;

            } elseif ($frame instanceof MethodBasicDeliverFrame) {
                $this->deliverFrame = $frame;
                $this->state = ChannelStateEnum::AWAITING_HEADER;

//            } elseif ($frame instanceof MethodBasicGetFrame) {
//            } elseif ($frame instanceof MethodBasicGetOkFrame) {
//            } elseif ($frame instanceof MethodBasicGetEmptyFrame) {
//            } elseif ($frame instanceof MethodBasicAckFrame) {
//            } elseif ($frame instanceof MethodBasicRejectFrame) {
//            } elseif ($frame instanceof MethodBasicRecoverAsyncFrame) {
//            } elseif ($frame instanceof MethodBasicRecoverFrame) {
//            } elseif ($frame instanceof MethodBasicRecoverOkFrame) {
//            } elseif ($frame instanceof MethodBasicNackFrame) {
//            } elseif ($frame instanceof MethodTxSelectFrame) {
//            } elseif ($frame instanceof MethodTxSelectOkFrame) {
//            } elseif ($frame instanceof MethodTxCommitFrame) {
//            } elseif ($frame instanceof MethodTxCommitOkFrame) {
//            } elseif ($frame instanceof MethodTxRollbackFrame) {
//            } elseif ($frame instanceof MethodTxRollbackOkFrame) {
//            } elseif ($frame instanceof MethodConfirmSelectFrame) {
//            } elseif ($frame instanceof MethodConfirmSelectOkFrame) {
            } else {
                throw new ChannelException("Unhandled method frame " . get_class($frame) . ".");
            }

        } elseif ($frame instanceof ContentHeaderFrame) {
            if ($this->state !== ChannelStateEnum::AWAITING_HEADER) {
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
            if ($this->state !== ChannelStateEnum::AWAITING_BODY) {
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
                $msg = new Message(
                    $this->deliverFrame->consumerTag,
                    $this->deliverFrame->deliveryTag,
                    $this->deliverFrame->redelivered,
                    $this->deliverFrame->exchange,
                    $this->deliverFrame->routingKey,
                    $this->headerFrame->toArray(),
                    $content
                );

                $callback = $this->deliverCallbacks[$this->deliverFrame->consumerTag];

                $callback($msg, $this, $this->client);
            }

            $this->deliverFrame = null;
            $this->headerFrame = null;

        } else {
            throw new \LogicException("Either return or deliver frame has to be handled here.");
        }
    }

}
