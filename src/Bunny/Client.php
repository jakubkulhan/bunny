<?php

namespace Bunny;

use Bunny\Exception\ClientException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\HeartbeatFrame;
use React\Promise;

/**
 * Synchronous AMQP/RabbitMQ client.
 *
 * The client's API follows AMQP class/method naming convention and uses PHP's idiomatic camelCase method naming
 * convention - e.g. "queue.declare" has corresponding method "queueDeclare", "exchange.delete" -> "exchangeDelete".
 * Methods from "basic" class are not prefixed with "basic" - e.g. "basic.publish" is just "publish".
 *
 * Usage:
 *
 *     $c = new Bunny\Client([
 *         "host" => "127.0.0.1",
 *         "port" => 5672,
 *         "vhost" => "/",
 *         "user" => "guest",
 *         "password" => "guest",
 *     ]);
 *
 *     $c->connect();
 *     // work with connected client, e.g. $c->channel()
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Client extends AbstractClient
{

    /** @var boolean */
    protected $running = true;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $options["async"] = false;
        parent::__construct($options);
    }

    /**
     * Destructor.
     *
     * Clean shutdown = disconnect if connected.
     */
    public function __destruct()
    {
        try {
        if ($this->isConnected()) {
                $this->disconnect()->done(function () {
                    $this->stop();
                });

                // has to re-check if connected, because disconnect() can set connection state immediately
                if ($this->isConnected()) {
                    $this->run();
                }
            }
        } catch (\Throwable $e) {
            // silently ignore - we dont want any exceptions to be thrown in __destruct as that will trigger fatal error
        } 
    }

    /**
     * Reads data from stream to {@link readBuffer}.
     *
     * @return boolean
     */
    protected function feedReadBuffer()
    {
        $this->read();
        return true;
    }

    /**
     * Writes all data from {@link writeBuffer} to stream.
     *
     * @return boolean
     */
    protected function flushWriteBuffer()
    {
        while (!$this->writeBuffer->isEmpty()) {
            $this->write();
        }
        return true;
    }

    /**
     * Synchronously connects to AMQP server.
     *
     * @throws \Exception
     * @return self
     */
    public function connect()
    {
        if ($this->state !== ClientStateEnum::NOT_CONNECTED) {
            throw new ClientException("Client already connected/connecting.");
        }

        try {
            $this->state = ClientStateEnum::CONNECTING;

            $this->writer->appendProtocolHeader($this->writeBuffer);
            $this->flushWriteBuffer();
            $this->authResponse($this->awaitConnectionStart());
            $tune = $this->awaitConnectionTune();
            $this->connectionTuneOk($tune->channelMax, $tune->frameMax, $this->options["heartbeat"]); // FIXME: options heartbeat
            $this->frameMax = $tune->frameMax;
            if ($tune->channelMax > 0) {
                $this->channelMax = $tune->channelMax;
            }
            $this->connectionOpen($this->options["vhost"]);

            $this->state = ClientStateEnum::CONNECTED;

            return $this;

        } catch (\Exception $e) {
            $this->state = ClientStateEnum::ERROR;
            throw $e;
        }
    }

    /**
     * Disconnects from AMQP server.
     *
     * @param int $replyCode
     * @param string $replyText
     * @return Promise\PromiseInterface
     */
    public function disconnect($replyCode = 0, $replyText = "")
    {
        if ($this->state === ClientStateEnum::DISCONNECTING) {
            return $this->disconnectPromise;
        }

        if ($this->state !== ClientStateEnum::CONNECTED) {
            return Promise\reject(new ClientException("Client is not connected."));
        }

        $this->state = ClientStateEnum::DISCONNECTING;

        $promises = [];

        if ($replyCode === 0) {
            foreach ($this->channels as $channel) {
                $promises[] = $channel->close();
            }
        }

        return $this->disconnectPromise = Promise\all($promises)->then(function () use ($replyCode, $replyText) {
            if (!empty($this->channels)) {
                throw new \LogicException("All channels have to be closed by now.");
            }

            $this->connectionClose($replyCode, $replyText, 0, 0);
            $this->closeStream();
            $this->init();
            return $this;
        });
    }

    /**
     * Runs it's own event loop, processes frames as they arrive. Processes messages for at most $maxSeconds.
     *
     * @param float $maxSeconds
     */
    public function run($maxSeconds = null)
    {
        if (!$this->isConnected()) {
            throw new ClientException("Client has to be connected.");
        }

        $this->running = true;
        $startTime = microtime(true);
        $stopTime = null;
        if ($maxSeconds !== null) {
            $stopTime = $startTime + $maxSeconds;
        }

        do {
            if (!empty($this->queue)) {
                $frame = array_shift($this->queue);

            } else {
                if (($frame = $this->reader->consumeFrame($this->readBuffer)) === null) {
                    $now = microtime(true);
                    $nextStreamSelectTimeout = $nextHeartbeat = ($this->lastWrite ?: $now) + $this->options["heartbeat"];
                    if ($stopTime !== null && $stopTime < $nextStreamSelectTimeout) {
                        $nextStreamSelectTimeout = $stopTime;
                    }
                    $tvSec = max(intval($nextStreamSelectTimeout - $now), 0);
                    $tvUsec = max(intval(($nextStreamSelectTimeout - $now - $tvSec) * 1000000), 0);

                    $r = [$this->getStream()];
                    $w = null;
                    $e = null;

                    if (($n = @stream_select($r, $w, $e, $tvSec, $tvUsec)) === false) {
                        $lastError = error_get_last();
                        if ($lastError !== null &&
                            preg_match("/^stream_select\\(\\): unable to select \\[(\\d+)\\]:/", $lastError["message"], $m) &&
                            intval($m[1]) === PCNTL_EINTR
                        ) {
                            // got interrupted by signal, dispatch signals & continue
                            pcntl_signal_dispatch();
                            $n = 0;

                        } else {
                            throw new ClientException(sprintf(
                                "stream_select() failed: %s",
                                $lastError ? $lastError["message"] : "Unknown error."
                            ));
                        }
                    }

                    $now = microtime(true);

                    if ($now >= $nextHeartbeat) {
                        $this->writer->appendFrame(new HeartbeatFrame(), $this->writeBuffer);
                        $this->flushWriteBuffer();

                        if (is_callable($this->options['heartbeat_callback'] ?? null)) {
                            $this->options['heartbeat_callback']->call($this);
                        }
                    }

                    if ($stopTime !== null && $now >= $stopTime) {
                        break;
                    }

                    if ($n > 0) {
                        $this->feedReadBuffer();
                    }

                    continue;
                }
            }

            /** @var AbstractFrame $frame */

            if ($frame->channel === 0) {
                $this->onFrameReceived($frame);

            } else {
                if (!isset($this->channels[$frame->channel])) {
                    throw new ClientException(
                        "Received frame #{$frame->type} on closed channel #{$frame->channel}."
                    );
                }

                $this->channels[$frame->channel]->onFrameReceived($frame);
            }


        } while ($this->running);
    }

    /**
     * Stops client's event loop.
     */
    public function stop()
    {
        $this->running = false;
    }

}
