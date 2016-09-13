<?php
namespace Bunny;

use Bunny\Exception\ClientException;
use Bunny\Protocol;
use Bunny\Protocol\Buffer;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * AMQP-0-9-1 client methods
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
trait ClientMethods
{

    /** @var array */
    private $cache = [];

    /**
     * Returns AMQP protocol reader.
     *
     * @return Protocol\ProtocolReader
     */
    abstract protected function getReader();

    /**
     * Returns read buffer.
     *
     * @return Buffer
     */
    abstract protected function getReadBuffer();

    /**
     * Returns AMQP protocol writer.
     *
     * @return Protocol\ProtocolWriter
     */
    abstract protected function getWriter();

    /**
     * Returns write buffer.
     *
     * @return Buffer
     */
    abstract protected function getWriteBuffer();

    /**
     * Reads data from stream to read buffer.
     */
    abstract protected function feedReadBuffer();

    /**
     * Writes all data from write buffer to stream.
     *
     * @return boolean|PromiseInterface
     */
    abstract protected function flushWriteBuffer();

    /**
     * Enqueues given frame for later processing.
     *
     * @param Protocol\AbstractFrame $frame
     */
    abstract protected function enqueue(Protocol\AbstractFrame $frame);

    /**
     * Returns frame max size.
     *
     * @return int
     */
    abstract protected function getFrameMax();

    /**
     * @param int $channel
     *
     * @return Protocol\ContentHeaderFrame|PromiseInterface
     */
    public function awaitContentHeader($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\ContentHeaderFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\ContentHeaderFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    /**
     * @param int $channel
     *
     * @return Protocol\ContentBodyFrame|PromiseInterface
     */
    public function awaitContentBody($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\ContentBodyFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\ContentBodyFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    /**
     * @return Protocol\MethodConnectionStartFrame|PromiseInterface
     */
    public function awaitConnectionStart()
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred) {
                if ($frame instanceof Protocol\MethodConnectionStartFrame) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConnectionStartFrame) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function connectionStartOk($clientProperties = [], $mechanism = 'PLAIN', $response, $locale = 'en_US')
    {
        $buffer = new Buffer();
        $buffer->appendUint16(10);
        $buffer->appendUint16(11);
        $this->getWriter()->appendTable($clientProperties, $buffer);
        $buffer->appendUint8(strlen($mechanism)); $buffer->append($mechanism);
        $buffer->appendUint32(strlen($response)); $buffer->append($response);
        $buffer->appendUint8(strlen($locale)); $buffer->append($locale);
        $frame = new Protocol\MethodFrame(10, 11);
        $frame->channel = 0;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->getWriter()->appendFrame($frame, $this->getWriteBuffer());
        return $this->flushWriteBuffer();
    }

    /**
     * @return Protocol\MethodConnectionSecureFrame|PromiseInterface
     */
    public function awaitConnectionSecure()
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred) {
                if ($frame instanceof Protocol\MethodConnectionSecureFrame) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConnectionSecureFrame) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function connectionSecureOk($response)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(8 + strlen($response));
        $buffer->appendUint16(10);
        $buffer->appendUint16(21);
        $buffer->appendUint32(strlen($response)); $buffer->append($response);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    /**
     * @return Protocol\MethodConnectionTuneFrame|PromiseInterface
     */
    public function awaitConnectionTune()
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred) {
                if ($frame instanceof Protocol\MethodConnectionTuneFrame) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConnectionTuneFrame) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function connectionTuneOk($channelMax = 0, $frameMax = 0, $heartbeat = 0)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(12);
        $buffer->appendUint16(10);
        $buffer->appendUint16(31);
        $buffer->appendInt16($channelMax);
        $buffer->appendInt32($frameMax);
        $buffer->appendInt16($heartbeat);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    public function connectionOpen($virtualHost = '/', $capabilities = '', $insist = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(7 + strlen($virtualHost) + strlen($capabilities));
        $buffer->appendUint16(10);
        $buffer->appendUint16(40);
        $buffer->appendUint8(strlen($virtualHost)); $buffer->append($virtualHost);
        $buffer->appendUint8(strlen($capabilities)); $buffer->append($capabilities);
        $this->getWriter()->appendBits([$insist], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitConnectionOpenOk();
    }

    /**
     * @return Protocol\MethodConnectionOpenOkFrame|PromiseInterface
     */
    public function awaitConnectionOpenOk()
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred) {
                if ($frame instanceof Protocol\MethodConnectionOpenOkFrame) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConnectionOpenOkFrame) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function connectionClose($replyCode, $replyText = '', $closeClassId, $closeMethodId)
    {
        $buffer = $this->getWriteBuffer();
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
     * @return Protocol\MethodConnectionCloseFrame|PromiseInterface
     */
    public function awaitConnectionClose()
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred) {
                if ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function connectionCloseOk()
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(4);
        $buffer->appendUint16(10);
        $buffer->appendUint16(51);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    /**
     * @return Protocol\MethodConnectionCloseOkFrame|PromiseInterface
     */
    public function awaitConnectionCloseOk()
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred) {
                if ($frame instanceof Protocol\MethodConnectionCloseOkFrame) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConnectionCloseOkFrame) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    /**
     * @return Protocol\MethodConnectionBlockedFrame|PromiseInterface
     */
    public function awaitConnectionBlocked()
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred) {
                if ($frame instanceof Protocol\MethodConnectionBlockedFrame) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConnectionBlockedFrame) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    /**
     * @return Protocol\MethodConnectionUnblockedFrame|PromiseInterface
     */
    public function awaitConnectionUnblocked()
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred) {
                if ($frame instanceof Protocol\MethodConnectionUnblockedFrame) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConnectionUnblockedFrame) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function channelOpen($channel, $outOfBand = '')
    {
        $buffer = $this->getWriteBuffer();
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
     * @return Protocol\MethodChannelOpenOkFrame|PromiseInterface
     */
    public function awaitChannelOpenOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodChannelOpenOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodChannelOpenOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function channelFlow($channel, $active)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(20);
        $buffer->appendUint16(20);
        $this->getWriter()->appendBits([$active], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitChannelFlowOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodChannelFlowFrame|PromiseInterface
     */
    public function awaitChannelFlow($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodChannelFlowFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodChannelFlowFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function channelFlowOk($channel, $active)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(20);
        $buffer->appendUint16(21);
        $this->getWriter()->appendBits([$active], $buffer);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodChannelFlowOkFrame|PromiseInterface
     */
    public function awaitChannelFlowOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodChannelFlowOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodChannelFlowOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function channelClose($channel, $replyCode, $replyText = '', $closeClassId, $closeMethodId)
    {
        $buffer = $this->getWriteBuffer();
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
        return $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodChannelCloseFrame|PromiseInterface
     */
    public function awaitChannelClose($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function channelCloseOk($channel)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(4);
        $buffer->appendUint16(20);
        $buffer->appendUint16(41);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodChannelCloseOkFrame|PromiseInterface
     */
    public function awaitChannelCloseOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodChannelCloseOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodChannelCloseOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function accessRequest($channel, $realm = '/data', $exclusive = false, $passive = true, $active = true, $write = true, $read = true)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(6 + strlen($realm));
        $buffer->appendUint16(30);
        $buffer->appendUint16(10);
        $buffer->appendUint8(strlen($realm)); $buffer->append($realm);
        $this->getWriter()->appendBits([$exclusive, $passive, $active, $write, $read], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitAccessRequestOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodAccessRequestOkFrame|PromiseInterface
     */
    public function awaitAccessRequestOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodAccessRequestOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodAccessRequestOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function exchangeDeclare($channel, $exchange, $exchangeType = 'direct', $passive = false, $durable = false, $autoDelete = false, $internal = false, $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(40);
        $buffer->appendUint16(10);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $buffer->appendUint8(strlen($exchangeType)); $buffer->append($exchangeType);
        $this->getWriter()->appendBits([$passive, $durable, $autoDelete, $internal, $nowait], $buffer);
        $this->getWriter()->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(40, 10);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->getWriter()->appendFrame($frame, $this->getWriteBuffer());
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitExchangeDeclareOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodExchangeDeclareOkFrame|PromiseInterface
     */
    public function awaitExchangeDeclareOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodExchangeDeclareOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodExchangeDeclareOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function exchangeDelete($channel, $exchange, $ifUnused = false, $nowait = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(8 + strlen($exchange));
        $buffer->appendUint16(40);
        $buffer->appendUint16(20);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $this->getWriter()->appendBits([$ifUnused, $nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitExchangeDeleteOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodExchangeDeleteOkFrame|PromiseInterface
     */
    public function awaitExchangeDeleteOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodExchangeDeleteOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodExchangeDeleteOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
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
        $this->getWriter()->appendBits([$nowait], $buffer);
        $this->getWriter()->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(40, 30);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->getWriter()->appendFrame($frame, $this->getWriteBuffer());
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitExchangeBindOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodExchangeBindOkFrame|PromiseInterface
     */
    public function awaitExchangeBindOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodExchangeBindOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodExchangeBindOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
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
        $this->getWriter()->appendBits([$nowait], $buffer);
        $this->getWriter()->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(40, 40);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->getWriter()->appendFrame($frame, $this->getWriteBuffer());
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitExchangeUnbindOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodExchangeUnbindOkFrame|PromiseInterface
     */
    public function awaitExchangeUnbindOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodExchangeUnbindOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodExchangeUnbindOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function queueDeclare($channel, $queue = '', $passive = false, $durable = false, $exclusive = false, $autoDelete = false, $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(50);
        $buffer->appendUint16(10);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->getWriter()->appendBits([$passive, $durable, $exclusive, $autoDelete, $nowait], $buffer);
        $this->getWriter()->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(50, 10);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->getWriter()->appendFrame($frame, $this->getWriteBuffer());
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitQueueDeclareOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodQueueDeclareOkFrame|PromiseInterface
     */
    public function awaitQueueDeclareOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodQueueDeclareOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodQueueDeclareOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function queueBind($channel, $queue = '', $exchange, $routingKey = '', $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(50);
        $buffer->appendUint16(20);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
        $this->getWriter()->appendBits([$nowait], $buffer);
        $this->getWriter()->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(50, 20);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->getWriter()->appendFrame($frame, $this->getWriteBuffer());
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitQueueBindOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodQueueBindOkFrame|PromiseInterface
     */
    public function awaitQueueBindOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodQueueBindOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodQueueBindOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function queuePurge($channel, $queue = '', $nowait = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(8 + strlen($queue));
        $buffer->appendUint16(50);
        $buffer->appendUint16(30);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->getWriter()->appendBits([$nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitQueuePurgeOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodQueuePurgeOkFrame|PromiseInterface
     */
    public function awaitQueuePurgeOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodQueuePurgeOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodQueuePurgeOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function queueDelete($channel, $queue = '', $ifUnused = false, $ifEmpty = false, $nowait = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(8 + strlen($queue));
        $buffer->appendUint16(50);
        $buffer->appendUint16(40);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->getWriter()->appendBits([$ifUnused, $ifEmpty, $nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitQueueDeleteOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodQueueDeleteOkFrame|PromiseInterface
     */
    public function awaitQueueDeleteOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodQueueDeleteOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodQueueDeleteOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function queueUnbind($channel, $queue = '', $exchange, $routingKey = '', $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(50);
        $buffer->appendUint16(50);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
        $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
        $this->getWriter()->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(50, 50);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->getWriter()->appendFrame($frame, $this->getWriteBuffer());
        $this->flushWriteBuffer();
        return $this->awaitQueueUnbindOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodQueueUnbindOkFrame|PromiseInterface
     */
    public function awaitQueueUnbindOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodQueueUnbindOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodQueueUnbindOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function qos($channel, $prefetchSize = 0, $prefetchCount = 0, $global = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(11);
        $buffer->appendUint16(60);
        $buffer->appendUint16(10);
        $buffer->appendInt32($prefetchSize);
        $buffer->appendInt16($prefetchCount);
        $this->getWriter()->appendBits([$global], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitQosOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicQosOkFrame|PromiseInterface
     */
    public function awaitQosOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicQosOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicQosOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function consume($channel, $queue = '', $consumerTag = '', $noLocal = false, $noAck = false, $exclusive = false, $nowait = false, $arguments = [])
    {
        $buffer = new Buffer();
        $buffer->appendUint16(60);
        $buffer->appendUint16(20);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $buffer->appendUint8(strlen($consumerTag)); $buffer->append($consumerTag);
        $this->getWriter()->appendBits([$noLocal, $noAck, $exclusive, $nowait], $buffer);
        $this->getWriter()->appendTable($arguments, $buffer);
        $frame = new Protocol\MethodFrame(60, 20);
        $frame->channel = $channel;
        $frame->payloadSize = $buffer->getLength();
        $frame->payload = $buffer;
        $this->getWriter()->appendFrame($frame, $this->getWriteBuffer());
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitConsumeOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicConsumeOkFrame|PromiseInterface
     */
    public function awaitConsumeOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicConsumeOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicConsumeOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function cancel($channel, $consumerTag, $nowait = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(6 + strlen($consumerTag));
        $buffer->appendUint16(60);
        $buffer->appendUint16(30);
        $buffer->appendUint8(strlen($consumerTag)); $buffer->append($consumerTag);
        $this->getWriter()->appendBits([$nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitCancelOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicCancelOkFrame|PromiseInterface
     */
    public function awaitCancelOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicCancelOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicCancelOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function publish($channel, $body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false)
    {
        $buffer = $this->getWriteBuffer();
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
        $this->getWriter()->appendBits([$mandatory, $immediate], $buffer);
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
            $this->getWriter()->appendTable($headers, $headersBuffer = new Buffer());
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
            $this->getWriter()->appendTimestamp($timestamp, $buffer);
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
        for ($payloadMax = $this->getFrameMax() - 8 /* frame preface and frame end */, $i = 0, $l = strlen($body); $i < $l; $i += $payloadMax) {
            $payloadSize = $l - $i; if ($payloadSize > $payloadMax) { $payloadSize = $payloadMax; }
            $buffer->appendUint8(3);
            $buffer->appendUint16($channel);
            $buffer->appendUint32($payloadSize);
            $buffer->append(substr($body, $i, $payloadSize));
            $buffer->appendUint8(206);
        }
        return $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicReturnFrame|PromiseInterface
     */
    public function awaitReturn($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicReturnFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicReturnFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicDeliverFrame|PromiseInterface
     */
    public function awaitDeliver($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicDeliverFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicDeliverFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function get($channel, $queue = '', $noAck = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(8 + strlen($queue));
        $buffer->appendUint16(60);
        $buffer->appendUint16(70);
        $buffer->appendInt16(0);
        $buffer->appendUint8(strlen($queue)); $buffer->append($queue);
        $this->getWriter()->appendBits([$noAck], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitGetOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicGetOkFrame|Protocol\MethodBasicGetEmptyFrame|PromiseInterface
     */
    public function awaitGetOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicGetOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodBasicGetEmptyFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicGetOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodBasicGetEmptyFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function ack($channel, $deliveryTag = 0, $multiple = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(80);
        $buffer->appendInt64($deliveryTag);
        $this->getWriter()->appendBits([$multiple], $buffer);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicAckFrame|PromiseInterface
     */
    public function awaitAck($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicAckFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicAckFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function reject($channel, $deliveryTag, $requeue = true)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(90);
        $buffer->appendInt64($deliveryTag);
        $this->getWriter()->appendBits([$requeue], $buffer);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    public function recoverAsync($channel, $requeue = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(60);
        $buffer->appendUint16(100);
        $this->getWriter()->appendBits([$requeue], $buffer);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    public function recover($channel, $requeue = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(60);
        $buffer->appendUint16(110);
        $this->getWriter()->appendBits([$requeue], $buffer);
        $buffer->appendUint8(206);
        $this->flushWriteBuffer();
        return $this->awaitRecoverOk($channel);
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicRecoverOkFrame|PromiseInterface
     */
    public function awaitRecoverOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicRecoverOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicRecoverOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function nack($channel, $deliveryTag = 0, $multiple = false, $requeue = true)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(120);
        $buffer->appendInt64($deliveryTag);
        $this->getWriter()->appendBits([$multiple, $requeue], $buffer);
        $buffer->appendUint8(206);
        return $this->flushWriteBuffer();
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodBasicNackFrame|PromiseInterface
     */
    public function awaitNack($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodBasicNackFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodBasicNackFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function txSelect($channel)
    {
        $buffer = $this->getWriteBuffer();
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
     * @return Protocol\MethodTxSelectOkFrame|PromiseInterface
     */
    public function awaitTxSelectOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodTxSelectOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodTxSelectOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function txCommit($channel)
    {
        $buffer = $this->getWriteBuffer();
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
     * @return Protocol\MethodTxCommitOkFrame|PromiseInterface
     */
    public function awaitTxCommitOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodTxCommitOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodTxCommitOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function txRollback($channel)
    {
        $buffer = $this->getWriteBuffer();
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
     * @return Protocol\MethodTxRollbackOkFrame|PromiseInterface
     */
    public function awaitTxRollbackOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodTxRollbackOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodTxRollbackOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

    public function confirmSelect($channel, $nowait = false)
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(85);
        $buffer->appendUint16(10);
        $this->getWriter()->appendBits([$nowait], $buffer);
        $buffer->appendUint8(206);
        if ($nowait) {
            return $this->flushWriteBuffer();
        } else {
            $this->flushWriteBuffer();
            return $this->awaitConfirmSelectOk($channel);
        }
    }

    /**
     * @param int $channel
     *
     * @return Protocol\MethodConfirmSelectOkFrame|PromiseInterface
     */
    public function awaitConfirmSelectOk($channel)
    {
        if ($this instanceof Async\Client) {
            $deferred = new Deferred();
            $this->addAwaitCallback(function ($frame) use ($deferred, $channel) {
                if ($frame instanceof Protocol\MethodConfirmSelectOkFrame && $frame->channel === $channel) {
                    $deferred->resolve($frame);
                    return true;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel)->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk()->done(function () use ($frame, $deferred) {
                        $deferred->reject(new ClientException($frame->replyText, $frame->replyCode));
                    });
                    return true;
                }
                return false;
            });
            return $deferred->promise();
        } else {
            for (;;) {
                while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                    $this->feedReadBuffer();
                }
                if ($frame instanceof Protocol\MethodConfirmSelectOkFrame && $frame->channel === $channel) {
                    return $frame;
                } elseif ($frame instanceof Protocol\MethodChannelCloseFrame && $frame->channel === $channel) {
                    $this->channelCloseOk($channel);
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } elseif ($frame instanceof Protocol\MethodConnectionCloseFrame) {
                    $this->connectionCloseOk();
                    throw new ClientException($frame->replyText, $frame->replyCode);
                } else {
                    $this->enqueue($frame);
                }
            }
        }
        throw new \LogicException('This statement should be never reached.');
    }

}
