<?php

namespace Bunny;

use Bunny\Exception\ClientException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodConnectionCloseFrame;
use Bunny\Protocol\MethodFrame;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;
use function React\Async\await;

/**
 * @internal
 */
final class Connection
{
    protected ?TimerInterface $heartbeatTimer = null;

    /** @var float microtime of last write */
    protected float $lastWrite = 0.0;

    private array $cache = [];

    /** @var array<array{filter: (callable(AbstractFrame): bool), promise: Deferred}> */
    private array $awaitList = [];

    private ?TimerInterface $queueProcessFutureTick = null;

    public function __construct(
        private readonly Client $client,
        private readonly ConnectionInterface $connection,
        private readonly Buffer $readBuffer,
        private readonly Buffer $writeBuffer,
        private readonly ProtocolReader $reader,
        private readonly ProtocolWriter $writer,
        private readonly Channels $channels,
        private readonly array $options = [],
    )
    {
        $this->connection->on('data', function (string $data): void {
            $this->readBuffer->append($data);

            while (($frame = $this->reader->consumeFrame($this->readBuffer)) !== null) {
                $frameInAwaitList = false;
                foreach ($this->awaitList as $index => $frameHandler) {
                    if ($frameHandler['filter']($frame)) {
                        unset($this->awaitList[$index]);
                        $frameHandler['promise']->resolve($frame);
                        $frameInAwaitList = true;
                    }
                }

                if ($frameInAwaitList) {
                    continue;
                }

                if ($frame->channel === 0) {
                    $this->onFrameReceived($frame);
                    continue;
                }

                if (!$this->channels->has($frame->channel)) {
                    throw new ClientException(
                        "Received frame #{$frame->type} on closed channel #{$frame->channel}."
                    );
                }

                $this->channels->get($frame->channel)->onFrameReceived($frame);
            }
        });
    }

    public function disconnect(int $code, string $reason)
    {
        $this->connectionClose($code, $reason, 0, 0);
        $this->connection->close();

        if ($this->heartbeatTimer === null) {
            return;
        }

        Loop::cancelTimer($this->heartbeatTimer);
    }

    /**
     * Callback after connection-level frame has been received.
     *
     * @param AbstractFrame $frame
     */
    private function onFrameReceived(AbstractFrame $frame)
    {
        if ($frame instanceof MethodFrame) {
            if ($frame instanceof MethodConnectionCloseFrame) {
                $this->disconnect(Constants::STATUS_CONNECTION_FORCED, "Connection closed by server: ({$frame->replyCode}) " . $frame->replyText);
                throw new ClientException("Connection closed by server: " . $frame->replyText, $frame->replyCode);
//            } else {
//                throw new ClientException("Unhandled method frame " . get_class($frame) . ".");
            }
        } elseif ($frame instanceof ContentHeaderFrame) {
            $this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got header frame on connection channel (#0).");
        } elseif ($frame instanceof ContentBodyFrame) {
            $this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got body frame on connection channel (#0).");
        } elseif ($frame instanceof HeartbeatFrame) {
//            $this->lastRead = microtime(true);
        } else {
            throw new ClientException("Unhandled frame " . get_class($frame) . ".");
        }
    }

    public function appendProtocolHeader(): void
    {
        $this->writer->appendProtocolHeader($this->writeBuffer);
    }

    public function flushWriteBuffer(): void
    {
        $data = $this->writeBuffer->read($this->writeBuffer->getLength());
        $this->writeBuffer->discard(strlen($data));

        $this->lastWrite = microtime(true);
        if (!$this->connection->write($data)) {
            await(new Promise(function (callable $resolve): void {
                $this->connection->once('drain', static fn () => $resolve(null));
            }));
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\ContentHeaderFrame
     */
    public function awaitContentHeader($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\ContentHeaderFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\ContentBodyFrame
     */
    public function awaitContentBody($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\ContentBodyFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    /**
     * @return \Bunny\Protocol\MethodConnectionStartFrame
     */
    public function awaitConnectionStart()
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConnectionStartFrame) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function connectionStartOk($clientProperties, $mechanism, $response, $locale = 'en_US')
    {
        $buffer = new Buffer();
        $buffer->appendUint16(10);
        $buffer->appendUint16(11);
        $this->writer->appendTable($clientProperties, $buffer);
        $buffer->appendUint8(strlen($mechanism)); $buffer->append($mechanism);
        $buffer->appendUint32(strlen($response)); $buffer->append($response);
        $buffer->appendUint8(strlen($locale)); $buffer->append($locale);
        $frame = new \Bunny\Protocol\MethodFrame(10, 11);
        $frame->channel = 0;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
    }

    /**
     * @return \Bunny\Protocol\MethodConnectionSecureFrame
     */
    public function awaitConnectionSecure()
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConnectionSecureFrame) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function connectionSecureOk($response)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(8 + strlen($response));
        $buffer->appendUint16(10);
        $buffer->appendUint16(21);
        $buffer->appendUint32(strlen($response)); $buffer->append($response);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    /**
     * @return \Bunny\Protocol\MethodConnectionTuneFrame
     */
    public function awaitConnectionTune()
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConnectionTuneFrame) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function connectionTuneOk($channelMax = 0, $frameMax = 0, $heartbeat = 0)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(12);
        $buffer->appendUint16(10);
        $buffer->appendUint16(31);
        $buffer->appendInt16($channelMax);
        $buffer->appendInt32($frameMax);
        $buffer->appendInt16($heartbeat);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    public function connectionOpen($virtualHost = '/', $capabilities = '', $insist = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(7 + strlen($virtualHost) + strlen($capabilities));
        $buffer->appendUint16(10);
        $buffer->appendUint16(40);
        $buffer->appendUint8(strlen($virtualHost)); $buffer->append($virtualHost);
        $buffer->appendUint8(strlen($capabilities)); $buffer->append($capabilities);
        $this->writer->appendBits([$insist], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitConnectionOpenOk();
    }

    /**
     * @return \Bunny\Protocol\MethodConnectionOpenOkFrame
     */
    public function awaitConnectionOpenOk()
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConnectionOpenOkFrame) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function connectionClose($replyCode, $replyText, $closeClassId, $closeMethodId)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(11 + strlen($replyText));
        $buffer->appendUint16(10);
        $buffer->appendUint16(50);
        $buffer->appendInt16($replyCode);
        $buffer->appendUint8(strlen($replyText)); $buffer->append($replyText);
        $buffer->appendInt16($closeClassId);
        $buffer->appendInt16($closeMethodId);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitConnectionCloseOk();
    }

    /**
     * @return \Bunny\Protocol\MethodConnectionCloseFrame
     */
    public function awaitConnectionClose()
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function connectionCloseOk()
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(4);
        $buffer->appendUint16(10);
        $buffer->appendUint16(51);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    /**
     * @return \Bunny\Protocol\MethodConnectionCloseOkFrame
     */
    public function awaitConnectionCloseOk()
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConnectionCloseOkFrame) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    /**
     * @return \Bunny\Protocol\MethodConnectionBlockedFrame
     */
    public function awaitConnectionBlocked()
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConnectionBlockedFrame) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    /**
     * @return \Bunny\Protocol\MethodConnectionUnblockedFrame
     */
    public function awaitConnectionUnblocked()
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConnectionUnblockedFrame) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function channelOpen($channel, $outOfBand = '')
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5 + strlen($outOfBand));
        $buffer->appendUint16(20);
        $buffer->appendUint16(10);
        $buffer->appendUint8(strlen($outOfBand)); $buffer->append($outOfBand);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitChannelOpenOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodChannelOpenOkFrame
     */
    public function awaitChannelOpenOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodChannelOpenOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function channelFlow($channel, $active)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(20);
        $buffer->appendUint16(20);
        $this->writer->appendBits([$active], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitChannelFlowOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodChannelFlowFrame
     */
    public function awaitChannelFlow($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodChannelFlowFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function channelFlowOk($channel, $active)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(20);
        $buffer->appendUint16(21);
        $this->writer->appendBits([$active], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodChannelFlowOkFrame
     */
    public function awaitChannelFlowOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodChannelFlowOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function channelClose($channel, $replyCode, $replyText, $closeClassId, $closeMethodId)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(11 + strlen($replyText));
        $buffer->appendUint16(20);
        $buffer->appendUint16(40);
        $buffer->appendInt16($replyCode);
        $buffer->appendUint8(strlen($replyText)); $buffer->append($replyText);
        $buffer->appendInt16($closeClassId);
        $buffer->appendInt16($closeMethodId);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodChannelCloseFrame
     */
    public function awaitChannelClose($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function channelCloseOk($channel)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(4);
        $buffer->appendUint16(20);
        $buffer->appendUint16(41);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodChannelCloseOkFrame
     */
    public function awaitChannelCloseOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodChannelCloseOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function accessRequest($channel, $realm = '/data', $exclusive = false, $passive = true, $active = true, $write = true, $read = true)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(6 + strlen($realm));
        $buffer->appendUint16(30);
        $buffer->appendUint16(10);
        $buffer->appendUint8(strlen($realm)); $buffer->append($realm);
        $this->writer->appendBits([$exclusive, $passive, $active, $write, $read], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitAccessRequestOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodAccessRequestOkFrame
     */
    public function awaitAccessRequestOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodAccessRequestOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function exchangeDeclare($channel, $exchange, $exchangeType = 'direct', $passive = false, $durable = false, $autoDelete = false, $internal = false, $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(40);
        $buffer->appendUint16(10);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $buffer->appendUint8(strlen($exchangeType)); $buffer->append($exchangeType);
        $this->writer->appendBits([$passive, $durable, $autoDelete, $internal, $nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new \Bunny\Protocol\MethodFrame(40, 10);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitExchangeDeclareOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodExchangeDeclareOkFrame
     */
    public function awaitExchangeDeclareOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodExchangeDeclareOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function exchangeDelete($channel, $exchange, $ifUnused = false, $nowait = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(8 + strlen($exchange));
        $buffer->appendUint16(40);
        $buffer->appendUint16(20);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $this->writer->appendBits([$ifUnused, $nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitExchangeDeleteOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodExchangeDeleteOkFrame
     */
    public function awaitExchangeDeleteOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodExchangeDeleteOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function exchangeBind($channel, $destination, $source, $routingKey = '', $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(40);
        $buffer->appendUint16(30);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($destination)); $buffer->append($destination);
        $buffer->appendUint8(strlen($source)); $buffer->append($source);
        $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
        $this->writer->appendBits([$nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new \Bunny\Protocol\MethodFrame(40, 30);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitExchangeBindOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodExchangeBindOkFrame
     */
    public function awaitExchangeBindOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodExchangeBindOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function exchangeUnbind($channel, $destination, $source, $routingKey = '', $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(40);
        $buffer->appendUint16(40);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($destination)); $buffer->append($destination);
        $buffer->appendUint8(strlen($source)); $buffer->append($source);
        $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
        $this->writer->appendBits([$nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new \Bunny\Protocol\MethodFrame(40, 40);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitExchangeUnbindOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodExchangeUnbindOkFrame
     */
    public function awaitExchangeUnbindOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodExchangeUnbindOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function queueDeclare($channel, $queue = '', $passive = false, $durable = false, $exclusive = false, $autoDelete = false, $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(50);
        $buffer->appendUint16(10);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->writer->appendBits([$passive, $durable, $exclusive, $autoDelete, $nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new \Bunny\Protocol\MethodFrame(50, 10);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitQueueDeclareOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodQueueDeclareOkFrame
     */
    public function awaitQueueDeclareOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodQueueDeclareOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function queueBind($channel, $queue, $exchange, $routingKey = '', $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(50);
        $buffer->appendUint16(20);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
        $this->writer->appendBits([$nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new \Bunny\Protocol\MethodFrame(50, 20);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitQueueBindOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodQueueBindOkFrame
     */
    public function awaitQueueBindOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodQueueBindOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function queuePurge($channel, $queue = '', $nowait = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(8 + strlen($queue));
        $buffer->appendUint16(50);
        $buffer->appendUint16(30);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->writer->appendBits([$nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitQueuePurgeOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodQueuePurgeOkFrame
     */
    public function awaitQueuePurgeOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodQueuePurgeOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function queueDelete($channel, $queue = '', $ifUnused = false, $ifEmpty = false, $nowait = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(8 + strlen($queue));
        $buffer->appendUint16(50);
        $buffer->appendUint16(40);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->writer->appendBits([$ifUnused, $ifEmpty, $nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitQueueDeleteOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodQueueDeleteOkFrame
     */
    public function awaitQueueDeleteOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodQueueDeleteOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function queueUnbind($channel, $queue, $exchange, $routingKey = '', $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(50);
        $buffer->appendUint16(50);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new \Bunny\Protocol\MethodFrame(50, 50);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        return $this->awaitQueueUnbindOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodQueueUnbindOkFrame
     */
    public function awaitQueueUnbindOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodQueueUnbindOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function qos($channel, $prefetchSize = 0, $prefetchCount = 0, $global = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(11);
        $buffer->appendUint16(60);
        $buffer->appendUint16(10);
        $buffer->appendInt32($prefetchSize);
        $buffer->appendInt16($prefetchCount);
        $this->writer->appendBits([$global], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitQosOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicQosOkFrame
     */
    public function awaitQosOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicQosOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function consume($channel, $queue = '', $consumerTag = '', $noLocal = false, $noAck = false, $exclusive = false, $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(60);
        $buffer->appendUint16(20);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $buffer->appendUint8(strlen($consumerTag)); $buffer->append($consumerTag);
        $this->writer->appendBits([$noLocal, $noAck, $exclusive, $nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new \Bunny\Protocol\MethodFrame(60, 20);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitConsumeOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicConsumeOkFrame
     */
    public function awaitConsumeOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicConsumeOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function cancel($channel, $consumerTag, $nowait = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(6 + strlen($consumerTag));
        $buffer->appendUint16(60);
        $buffer->appendUint16(30);
        $buffer->appendUint8(strlen($consumerTag)); $buffer->append($consumerTag);
        $this->writer->appendBits([$nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitCancelOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicCancelOkFrame
     */
    public function awaitCancelOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicCancelOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function publish($channel, $body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false)
    {
        $buffer = $this->writeBuffer;
        $ck = serialize([$channel, $headers, $exchange, $routingKey, $mandatory, $immediate]);
        $c = isset($this->cache[$ck]) ? $this->cache[$ck] : null;
        $flags = 0; $off0 = 0; $len0 = 0; $off1 = 0; $len1 = 0; $contentTypeLength = null; $contentType = null; $contentEncodingLength = null; $contentEncoding = null; $headersBuffer = null; $deliveryMode = null; $priority = null; $correlationIdLength = null; $correlationId = null; $replyToLength = null; $replyTo = null; $expirationLength = null; $expiration = null; $messageIdLength = null; $messageId = null; $timestamp = null; $typeLength = null; $type = null; $userIdLength = null; $userId = null; $appIdLength = null; $appId = null; $clusterIdLength = null; $clusterId = null;
        if ($c) { $buffer->append($c[0]); }
        else {
            $off0 = $buffer->getLength();
            $buffer->appendUint8(1);
            $buffer->appendUint16($channel);
            $buffer->appendUint32(9 + strlen($exchange) + strlen($routingKey));
            $buffer->appendUint16(60);
            $buffer->appendUint16(40);
            $buffer->appendInt16(0);
            $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
            $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
            $this->writer->appendBits([$mandatory, $immediate], $buffer);
            $buffer->appendUint8(206);
            $s = 14;
            if (isset($headers['content-type'])) {
                $flags |= 32768;
                $contentType = $headers['content-type'];
                $s += 1;
                $s += $contentTypeLength = strlen($contentType);
                unset($headers['content-type']);
            }
            if (isset($headers['content-encoding'])) {
                $flags |= 16384;
                $contentEncoding = $headers['content-encoding'];
                $s += 1;
                $s += $contentEncodingLength = strlen($contentEncoding);
                unset($headers['content-encoding']);
            }
            if (isset($headers['delivery-mode'])) {
                $flags |= 4096;
                $deliveryMode = $headers['delivery-mode'];
                $s += 1;
                unset($headers['delivery-mode']);
            }
            if (isset($headers['priority'])) {
                $flags |= 2048;
                $priority = $headers['priority'];
                $s += 1;
                unset($headers['priority']);
            }
            if (isset($headers['correlation-id'])) {
                $flags |= 1024;
                $correlationId = $headers['correlation-id'];
                $s += 1;
                $s += $correlationIdLength = strlen($correlationId);
                unset($headers['correlation-id']);
            }
            if (isset($headers['reply-to'])) {
                $flags |= 512;
                $replyTo = $headers['reply-to'];
                $s += 1;
                $s += $replyToLength = strlen($replyTo);
                unset($headers['reply-to']);
            }
            if (isset($headers['expiration'])) {
                $flags |= 256;
                $expiration = $headers['expiration'];
                $s += 1;
                $s += $expirationLength = strlen($expiration);
                unset($headers['expiration']);
            }
            if (isset($headers['message-id'])) {
                $flags |= 128;
                $messageId = $headers['message-id'];
                $s += 1;
                $s += $messageIdLength = strlen($messageId);
                unset($headers['message-id']);
            }
            if (isset($headers['timestamp'])) {
                $flags |= 64;
                $timestamp = $headers['timestamp'];
                $s += 8;
                unset($headers['timestamp']);
            }
            if (isset($headers['type'])) {
                $flags |= 32;
                $type = $headers['type'];
                $s += 1;
                $s += $typeLength = strlen($type);
                unset($headers['type']);
            }
            if (isset($headers['user-id'])) {
                $flags |= 16;
                $userId = $headers['user-id'];
                $s += 1;
                $s += $userIdLength = strlen($userId);
                unset($headers['user-id']);
            }
            if (isset($headers['app-id'])) {
                $flags |= 8;
                $appId = $headers['app-id'];
                $s += 1;
                $s += $appIdLength = strlen($appId);
                unset($headers['app-id']);
            }
            if (isset($headers['cluster-id'])) {
                $flags |= 4;
                $clusterId = $headers['cluster-id'];
                $s += 1;
                $s += $clusterIdLength = strlen($clusterId);
                unset($headers['cluster-id']);
            }
            if (!empty($headers)) {
                $flags |= 8192;
                $this->writer->appendTable($headers, $headersBuffer = new Buffer());
                $s += $headersBuffer->getLength();
            }
            $buffer->appendUint8(2);
            $buffer->appendUint16($channel);
            $buffer->appendUint32($s);
            $buffer->appendUint16(60);
            $buffer->appendUint16(0);
            $len0 = $buffer->getLength() - $off0;
        }
        $buffer->appendUint64(strlen($body));
        if ($c) { $buffer->append($c[1]); }
        else {
            $off1 = $buffer->getLength();
            $buffer->appendUint16($flags);
            if ($flags & 32768) {
                $buffer->appendUint8($contentTypeLength); $buffer->append($contentType);
            }
            if ($flags & 16384) {
                $buffer->appendUint8($contentEncodingLength); $buffer->append($contentEncoding);
            }
            if ($flags & 8192) {
                $buffer->append($headersBuffer);
            }
            if ($flags & 4096) {
                $buffer->appendUint8($deliveryMode);
            }
            if ($flags & 2048) {
                $buffer->appendUint8($priority);
            }
            if ($flags & 1024) {
                $buffer->appendUint8($correlationIdLength); $buffer->append($correlationId);
            }
            if ($flags & 512) {
                $buffer->appendUint8($replyToLength); $buffer->append($replyTo);
            }
            if ($flags & 256) {
                $buffer->appendUint8($expirationLength); $buffer->append($expiration);
            }
            if ($flags & 128) {
                $buffer->appendUint8($messageIdLength); $buffer->append($messageId);
            }
            if ($flags & 64) {
                $this->writer->appendTimestamp($timestamp, $buffer);
            }
            if ($flags & 32) {
                $buffer->appendUint8($typeLength); $buffer->append($type);
            }
            if ($flags & 16) {
                $buffer->appendUint8($userIdLength); $buffer->append($userId);
            }
            if ($flags & 8) {
                $buffer->appendUint8($appIdLength); $buffer->append($appId);
            }
            if ($flags & 4) {
                $buffer->appendUint8($clusterIdLength); $buffer->append($clusterId);
            }
            $buffer->appendUint8(206);
            $len1 = $buffer->getLength() - $off1;
        }
        if (!$c) {
            $this->cache[$ck] = [$buffer->read($len0, $off0), $buffer->read($len1, $off1)];
            if (count($this->cache) > 100) { reset($this->cache); unset($this->cache[key($this->cache)]); }
        }
        for ($payloadMax = $this->client->frameMax - 8 /* frame preface and frame end */, $i = 0, $l = strlen($body); $i < $l; $i += $payloadMax) {
            $payloadSize = $l - $i; if ($payloadSize > $payloadMax) { $payloadSize = $payloadMax; }
            $buffer->appendUint8(3);
            $buffer->appendUint16($channel);
            $buffer->appendUint32($payloadSize);
            $buffer->append(substr($body, $i, $payloadSize));
            $buffer->appendUint8(206);
        }
        $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicReturnFrame
     */
    public function awaitReturn($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicReturnFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicDeliverFrame
     */
    public function awaitDeliver($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicDeliverFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function get($channel, $queue = '', $noAck = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(8 + strlen($queue));
        $buffer->appendUint16(60);
        $buffer->appendUint16(70);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->writer->appendBits([$noAck], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitGetOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicGetOkFrame|\Bunny\Protocol\MethodBasicGetEmptyFrame
     */
    public function awaitGetOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicGetOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodBasicGetEmptyFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function ack($channel, $deliveryTag = 0, $multiple = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(80);
        $buffer->appendInt64($deliveryTag);
        $this->writer->appendBits([$multiple], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicAckFrame
     */
    public function awaitAck($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicAckFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function reject($channel, $deliveryTag, $requeue = true)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(90);
        $buffer->appendInt64($deliveryTag);
        $this->writer->appendBits([$requeue], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    public function recoverAsync($channel, $requeue = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(60);
        $buffer->appendUint16(100);
        $this->writer->appendBits([$requeue], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    public function recover($channel, $requeue = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(60);
        $buffer->appendUint16(110);
        $this->writer->appendBits([$requeue], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitRecoverOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicRecoverOkFrame
     */
    public function awaitRecoverOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicRecoverOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function nack($channel, $deliveryTag = 0, $multiple = false, $requeue = true)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(120);
        $buffer->appendInt64($deliveryTag);
        $this->writer->appendBits([$multiple, $requeue], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodBasicNackFrame
     */
    public function awaitNack($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodBasicNackFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function txSelect($channel)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(4);
        $buffer->appendUint16(90);
        $buffer->appendUint16(10);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitTxSelectOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodTxSelectOkFrame
     */
    public function awaitTxSelectOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodTxSelectOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function txCommit($channel)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(4);
        $buffer->appendUint16(90);
        $buffer->appendUint16(20);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitTxCommitOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodTxCommitOkFrame
     */
    public function awaitTxCommitOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodTxCommitOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function txRollback($channel)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(4);
        $buffer->appendUint16(90);
        $buffer->appendUint16(30);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitTxRollbackOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodTxRollbackOkFrame
     */
    public function awaitTxRollbackOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodTxRollbackOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function confirmSelect($channel, $nowait = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(85);
        $buffer->appendUint16(10);
        $this->writer->appendBits([$nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitConfirmSelectOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return \Bunny\Protocol\MethodConfirmSelectOkFrame
     */
    public function awaitConfirmSelectOk($channel)
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof \Bunny\Protocol\MethodConfirmSelectOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof \Bunny\Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof \Bunny\Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function startHeathbeatTimer(): void
    {
        $this->heartbeatTimer = Loop::addTimer($this->options["heartbeat"], [$this, "onHeartbeat"]);
        $this->connection->on('drain', [$this, "onHeartbeat"]);
    }

    /**
     * Callback when heartbeat timer timed out.
     */
    public function onHeartbeat()
    {
        $now = microtime(true);
        $nextHeartbeat = ($this->lastWrite ?: $now) + $this->options["heartbeat"];

        if ($now >= $nextHeartbeat) {
            $this->writer->appendFrame(new HeartbeatFrame(), $this->writeBuffer);
            $this->flushWriteBuffer();

            $this->heartbeatTimer = Loop::addTimer($this->options["heartbeat"], [$this, "onHeartbeat"]);
            if (is_callable($this->options['heartbeat_callback'] ?? null)) {
                $this->options['heartbeat_callback']->call($this);
            }
        } else {
            $this->heartbeatTimer = Loop::addTimer($nextHeartbeat - $now, [$this, "onHeartbeat"]);
        }
    }
}
