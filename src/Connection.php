<?php

declare(strict_types=1);

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
 * AMQP-0-9-1 client methods
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class Connection
{
    protected ?TimerInterface $heartbeatTimer = null;

    /** @var float microtime of last write */
    protected float $lastWrite = 0.0;

    private array $cache = [];

    /** @var array<array{filter: (callable(AbstractFrame): bool), promise: Deferred}> */
    private array $awaitList = [];

    public function __construct(
        private readonly Client $client,
        private readonly ConnectionInterface $connection,
        private readonly Buffer $readBuffer,
        private readonly Buffer $writeBuffer,
        private readonly ProtocolReader $reader,
        private readonly ProtocolWriter $writer,
        private readonly Channels $channels,
        private readonly array $options = [],
    ) {
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
        $this->connectionClose($code, 0, 0, $reason);
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
                throw new ClientException('Connection closed by server: ' . $frame->replyText, $frame->replyCode);
            }
        } elseif ($frame instanceof ContentHeaderFrame) {
            $this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, 'Got header frame on connection channel (#0).');
        } elseif ($frame instanceof ContentBodyFrame) {
            $this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, 'Got body frame on connection channel (#0).');
        } elseif ($frame instanceof HeartbeatFrame) {
            return;
        }

        throw new ClientException('Unhandled frame ' . get_class($frame) . '.');
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

    public function awaitContentHeader(int $channel): ContentHeaderFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\ContentHeaderFrame && $frame->channel === $channel) {
                    return true;
    } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }

    public function awaitContentBody(int $channel): ContentBodyFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\ContentBodyFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }

                return false;
            },
            'promise' => $deferred,
        ];

        return await($deferred->promise());
    }
    public function awaitConnectionStart(): Protocol\MethodConnectionStartFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame): bool {
                if ($frame instanceof Protocol\MethodConnectionStartFrame) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function connectionStartOk(string $response, array $clientProperties = [], string $mechanism = 'PLAIN', string $locale = 'en_US'): bool
    {
        $buffer = new Buffer();
        $buffer->appendUint16(10);
        $buffer->appendUint16(11);
        $this->writer->appendTable($clientProperties, $buffer);
        $buffer->appendUint8(strlen($mechanism)); $buffer->append($mechanism);
        $buffer->appendUint32(strlen($response)); $buffer->append($response);
        $buffer->appendUint8(strlen($locale)); $buffer->append($locale);
        $frame = new Protocol\MethodFrame(10, 11);
        $frame->channel = 0;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        return false;
    }

    public function awaitConnectionSecure(): Protocol\MethodConnectionSecureFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame): bool {
                if ($frame instanceof Protocol\MethodConnectionSecureFrame) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function connectionSecureOk(string $response): bool
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
        return false;
    }

    public function awaitConnectionTune(): Protocol\MethodConnectionTuneFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame): bool {
                if ($frame instanceof Protocol\MethodConnectionTuneFrame) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function connectionTuneOk(int $channelMax = 0, int $frameMax = 0, int $heartbeat = 0): bool
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
        return false;
    }

    public function connectionOpen(string $virtualHost = '/', string $capabilities = '', bool $insist = false): bool|Protocol\MethodConnectionOpenOkFrame
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

    public function awaitConnectionOpenOk(): Protocol\MethodConnectionOpenOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame): bool {
                if ($frame instanceof Protocol\MethodConnectionOpenOkFrame) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function connectionClose(int $replyCode, int $closeClassId, int $closeMethodId, string $replyText = ''): bool|Protocol\MethodConnectionCloseOkFrame
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

    public function awaitConnectionClose(): Protocol\MethodConnectionCloseFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame): bool {
                return $frame instanceof Protocol\MethodConnectionCloseFrame;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function connectionCloseOk(): bool
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(4);
        $buffer->appendUint16(10);
        $buffer->appendUint16(51);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return false;
    }

    public function awaitConnectionCloseOk(): Protocol\MethodConnectionCloseOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame): bool {
                if ($frame instanceof Protocol\MethodConnectionCloseOkFrame) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function awaitConnectionBlocked(): Protocol\MethodConnectionBlockedFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame): bool {
                if ($frame instanceof Protocol\MethodConnectionBlockedFrame) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function awaitConnectionUnblocked(): Protocol\MethodConnectionUnblockedFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame): bool {
                if ($frame instanceof Protocol\MethodConnectionUnblockedFrame) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function channelOpen(int $channel, string $outOfBand = ''): bool|Protocol\MethodChannelOpenOkFrame
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

    public function awaitChannelOpenOk(int $channel): Protocol\MethodChannelOpenOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodChannelOpenOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function channelFlow(int $channel, bool $active): bool|Protocol\MethodChannelFlowOkFrame
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

    public function awaitChannelFlow(int $channel): Protocol\MethodChannelFlowFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodChannelFlowFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function channelFlowOk(int $channel, bool $active): bool
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
        return false;
    }

    public function awaitChannelFlowOk(int $channel): Protocol\MethodChannelFlowOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodChannelFlowOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function channelClose(int $channel, int $replyCode, int $closeClassId, int $closeMethodId, string $replyText = ''): bool
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
        return false;
    }

    public function awaitChannelClose(int $channel): Protocol\MethodChannelCloseFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function channelCloseOk(int $channel): bool
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(4);
        $buffer->appendUint16(20);
        $buffer->appendUint16(41);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return false;
    }

    public function awaitChannelCloseOk(int $channel): Protocol\MethodChannelCloseOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodChannelCloseOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function accessRequest(int $channel, string $realm = '/data', bool $exclusive = false, bool $passive = true, bool $active = true, bool $write = true, bool $read = true): bool|Protocol\MethodAccessRequestOkFrame
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

    public function awaitAccessRequestOk(int $channel): Protocol\MethodAccessRequestOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodAccessRequestOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function exchangeDeclare(int $channel, string $exchange, string $exchangeType = 'direct', bool $passive = false, bool $durable = false, bool $autoDelete = false, bool $internal = false, bool $nowait = false, array $arguments = []): bool|Protocol\MethodExchangeDeclareOkFrame
    {
        $buffer = new Buffer();
        $buffer->appendUint16(40);
        $buffer->appendUint16(10);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $buffer->appendUint8(strlen($exchangeType)); $buffer->append($exchangeType);
        $this->writer->appendBits([$passive, $durable, $autoDelete, $internal, $nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(40, 10);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitExchangeDeclareOk($channel);
        }
        return false;
    }

    public function awaitExchangeDeclareOk(int $channel): Protocol\MethodExchangeDeclareOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodExchangeDeclareOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function exchangeDelete(int $channel, string $exchange, bool $ifUnused = false, bool $nowait = false): bool|Protocol\MethodExchangeDeleteOkFrame
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
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitExchangeDeleteOk($channel);
        }
        return false;
    }

    public function awaitExchangeDeleteOk(int $channel): Protocol\MethodExchangeDeleteOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodExchangeDeleteOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function exchangeBind(int $channel, string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): bool|Protocol\MethodExchangeBindOkFrame
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
        $frame = new Protocol\MethodFrame(40, 30);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitExchangeBindOk($channel);
        }
        return false;
    }

    public function awaitExchangeBindOk(int $channel): Protocol\MethodExchangeBindOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodExchangeBindOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function exchangeUnbind(int $channel, string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): bool|Protocol\MethodExchangeUnbindOkFrame
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
        $frame = new Protocol\MethodFrame(40, 40);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitExchangeUnbindOk($channel);
        }
        return false;
    }

    public function awaitExchangeUnbindOk(int $channel): Protocol\MethodExchangeUnbindOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodExchangeUnbindOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function queueDeclare(int $channel, string $queue = '', bool $passive = false, bool $durable = false, bool $exclusive = false, bool $autoDelete = false, bool $nowait = false, array $arguments = []): bool|Protocol\MethodQueueDeclareOkFrame
    {
        $buffer = new Buffer();
        $buffer->appendUint16(50);
        $buffer->appendUint16(10);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->writer->appendBits([$passive, $durable, $exclusive, $autoDelete, $nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(50, 10);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitQueueDeclareOk($channel);
        }
        return false;
    }

    public function awaitQueueDeclareOk(int $channel): Protocol\MethodQueueDeclareOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodQueueDeclareOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function queueBind(int $channel, string $exchange, string $queue = '', string $routingKey = '', bool $nowait = false, array $arguments = []): bool|Protocol\MethodQueueBindOkFrame
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
        $frame = new Protocol\MethodFrame(50, 20);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitQueueBindOk($channel);
        }
        return false;
    }

    public function awaitQueueBindOk(int $channel): Protocol\MethodQueueBindOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodQueueBindOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function queuePurge(int $channel, string $queue = '', bool $nowait = false): bool|Protocol\MethodQueuePurgeOkFrame
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
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitQueuePurgeOk($channel);
        }
        return false;
    }

    public function awaitQueuePurgeOk(int $channel): Protocol\MethodQueuePurgeOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodQueuePurgeOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function queueDelete(int $channel, string $queue = '', bool $ifUnused = false, bool $ifEmpty = false, bool $nowait = false): bool|Protocol\MethodQueueDeleteOkFrame
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
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitQueueDeleteOk($channel);
        }
        return false;
    }

    public function awaitQueueDeleteOk(int $channel): Protocol\MethodQueueDeleteOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodQueueDeleteOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function queueUnbind(int $channel, string $exchange, string $queue = '', string $routingKey = '', array $arguments = []): bool|Protocol\MethodQueueUnbindOkFrame
    {
        $buffer = new Buffer();
        $buffer->appendUint16(50);
        $buffer->appendUint16(50);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(50, 50);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        return $this->awaitQueueUnbindOk($channel);
    }

    public function awaitQueueUnbindOk(int $channel): Protocol\MethodQueueUnbindOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodQueueUnbindOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function qos(int $channel, int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): bool|Protocol\MethodBasicQosOkFrame
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

    public function awaitQosOk(int $channel): Protocol\MethodBasicQosOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicQosOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function consume(int $channel, string $queue = '', string $consumerTag = '', bool $noLocal = false, bool $noAck = false, bool $exclusive = false, bool $nowait = false, array $arguments = []): bool|Protocol\MethodBasicConsumeOkFrame
    {
        $buffer = new Buffer();
        $buffer->appendUint16(60);
        $buffer->appendUint16(20);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $buffer->appendUint8(strlen($consumerTag)); $buffer->append($consumerTag);
        $this->writer->appendBits([$noLocal, $noAck, $exclusive, $nowait], $buffer);
        $this->writer->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(60, 20);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->writer->appendFrame($frame, $this->writeBuffer);
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitConsumeOk($channel);
        }
        return false;
    }

    public function awaitConsumeOk(int $channel): Protocol\MethodBasicConsumeOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicConsumeOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function cancel(int $channel, string $consumerTag, bool $nowait = false): bool|Protocol\MethodBasicCancelOkFrame
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
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitCancelOk($channel);
        }
        return false;
    }

    public function awaitCancelOk(int $channel): Protocol\MethodBasicCancelOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicCancelOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function publish(int $channel, string $body, array $headers = [], string $exchange = '', string $routingKey = '', bool $mandatory = false, bool $immediate = false): bool
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
        return false;
    }

    public function awaitReturn(int $channel): Protocol\MethodBasicReturnFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicReturnFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function awaitDeliver(int $channel): Protocol\MethodBasicDeliverFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicDeliverFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function get(int $channel, string $queue = '', bool $noAck = false): bool|Protocol\MethodBasicGetOkFrame|Protocol\MethodBasicGetEmptyFrame
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

    public function awaitGetOk(int $channel): Protocol\MethodBasicGetOkFrame|Protocol\MethodBasicGetEmptyFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicGetOkFrame && $frame->channel === $channel) {
                    return true;
            } elseif ($frame instanceof Protocol\MethodBasicGetEmptyFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function ack(int $channel, int $deliveryTag = 0, bool $multiple = false): bool
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
        return false;
    }

    public function awaitAck(int $channel): Protocol\MethodBasicAckFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicAckFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function reject(int $channel, int $deliveryTag, bool $requeue = true): bool
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
        return false;
    }

    public function recoverAsync(int $channel, bool $requeue = false): bool
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
        return false;
    }

    public function recover(int $channel, bool $requeue = false): bool|Protocol\MethodBasicRecoverOkFrame
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

    public function awaitRecoverOk(int $channel): Protocol\MethodBasicRecoverOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicRecoverOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function nack(int $channel, int $deliveryTag = 0, bool $multiple = false, bool $requeue = true): bool
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
        return false;
    }

    public function awaitNack(int $channel): Protocol\MethodBasicNackFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodBasicNackFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function txSelect(int $channel): bool|Protocol\MethodTxSelectOkFrame
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

    public function awaitTxSelectOk(int $channel): Protocol\MethodTxSelectOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodTxSelectOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function txCommit(int $channel): bool|Protocol\MethodTxCommitOkFrame
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

    public function awaitTxCommitOk(int $channel): Protocol\MethodTxCommitOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodTxCommitOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function txRollback(int $channel): bool|Protocol\MethodTxRollbackOkFrame
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

    public function awaitTxRollbackOk(int $channel): Protocol\MethodTxRollbackOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodTxRollbackOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                }
                return false;
          },
          'promise' => $deferred,
        ];
        return await($deferred->promise());
    }

    public function confirmSelect(int $channel, bool $nowait = false): bool|Protocol\MethodConfirmSelectOkFrame
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(85);
        $buffer->appendUint16(10);
        $this->writer->appendBits([$nowait], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        if (!$nowait) {
            return $this->awaitConfirmSelectOk($channel);
        }
        return false;
    }

    public function awaitConfirmSelectOk(int $channel): Protocol\MethodConfirmSelectOkFrame
    {
        $deferred = new Deferred();
        $this->awaitList[] = [
            'filter' => function (Protocol\AbstractFrame $frame) use ($channel): bool {
                if ($frame instanceof Protocol\MethodConfirmSelectOkFrame && $frame->channel === $channel) {
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
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
        $this->heartbeatTimer = Loop::addTimer($this->options['heartbeat'], [$this, 'onHeartbeat']);
        $this->connection->on('drain', [$this, 'onHeartbeat']);
    }

    /**
     * Callback when heartbeat timer timed out.
     */
    public function onHeartbeat()
    {
        $now = microtime(true);
        $nextHeartbeat = ($this->lastWrite ?: $now) + $this->options['heartbeat'];

        if ($now >= $nextHeartbeat) {
            $this->writer->appendFrame(new HeartbeatFrame(), $this->writeBuffer);
            $this->flushWriteBuffer();

            $this->heartbeatTimer = Loop::addTimer($this->options['heartbeat'], [$this, 'onHeartbeat']);
            if (is_callable($this->options['heartbeat_callback'] ?? null)) {
                $this->options['heartbeat_callback']($this);
            }
        } else {
            $this->heartbeatTimer = Loop::addTimer($nextHeartbeat - $now, [$this, 'onHeartbeat']);
        }
    }
}
