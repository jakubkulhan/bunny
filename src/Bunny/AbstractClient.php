<?php
namespace Bunny;

use Bunny\Exception\ClientException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodChannelOpenOkFrame;
use Bunny\Protocol\MethodConnectionCloseFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use Bunny\Protocol\MethodFrame;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;
use Psr\Log\LoggerInterface;
use React\Promise;

/**
 * Base class for synchronous and asynchronous AMQP/RabbitMQ client.
 *
 * The client's API follows AMQP class/method naming convention and uses PHP's idiomatic camelCase method naming
 * convention - e.g. "queue.declare" has corresponding method "queueDeclare", "exchange.delete" -> "exchangeDelete".
 * Methods from "basic" class are not prefixed with "basic" - e.g. "basic.publish" is just "publish".
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
abstract class AbstractClient
{

    use ClientMethods;

    /** @var array */
    protected $options;

    /** @var LoggerInterface */
    protected $log;

    /** @var resource */
    protected $stream;

    /** @var int */
    protected $state = ClientStateEnum::NOT_CONNECTED;

    /** @var Buffer */
    protected $readBuffer;

    /** @var Buffer */
    protected $writeBuffer;

    /** @var ProtocolReader */
    protected $reader;

    /** @var ProtocolWriter */
    protected $writer;

    /** @var AbstractFrame[] */
    protected $queue;

    /** @var Channel[] */
    protected $channels = [];

    /** @var Promise\PromiseInterface */
    protected $disconnectPromise;

    /** @var int */
    protected $frameMax = 0xFFFF;

    /** @var float microtime of last read*/
    protected $lastRead = 0.0;

    /** @var float microtime of last write */
    protected $lastWrite = 0.0;

    /**
     * Constructor.
     *
     * @param array $options
     * @param LoggerInterface $log
     */
    public function __construct(array $options = [], LoggerInterface $log = null)
    {
        if (!isset($options["host"])) {
            $options["host"] = "127.0.0.1";
        }

        if (!isset($options["port"])) {
            $options["port"] = 5672;
        }

        if (!isset($options["vhost"])) {
            if (isset($options["virtual_host"])) {
                $options["vhost"] = $options["virtual_host"];
                unset($options["virtual_host"]);
            } elseif (isset($options["path"])) {
                $options["vhost"] = $options["path"];
                unset($options["path"]);
            } else {
                $options["vhost"] = "/";
            }
        }

        if (!isset($options["user"])) {
            if (isset($options["username"])) {
                $options["user"] = $options["username"];
                unset($options["username"]);
            } else {
                $options["user"] = "guest";
            }
        }

        if (!isset($options["password"])) {
            if (isset($options["pass"])) {
                $options["password"] = $options["pass"];
                unset($options["pass"]);
            } else {
                $options["password"] = "guest";
            }
        }

        if (!isset($options["timeout"])) {
            $options["timeout"] = 1;
        }

        if (!isset($options["heartbeat"])) {
            $options["heartbeat"] = 60.0;
        }

        $this->options = $options;
        $this->log = $log;

        $this->init();
    }

    /**
     * Initializes instance.
     */
    protected function init()
    {
        $this->state = ClientStateEnum::NOT_CONNECTED;
        $this->readBuffer = new Buffer();
        $this->writeBuffer = new Buffer();
        $this->reader = new ProtocolReader();
        $this->writer = new ProtocolWriter();
        $this->queue = [];
    }

    /**
     * Returns AMQP protocol reader.
     *
     * @return ProtocolReader
     */
    protected function getReader()
    {
        return $this->reader;
    }

    /**
     * Returns AMQP protocol writer.
     *
     * @return ProtocolWriter
     */
    protected function getWriter()
    {
        return $this->writer;
    }

    /**
     * Returns read buffer.
     *
     * @return Buffer
     */
    protected function getReadBuffer()
    {
        return $this->readBuffer;
    }

    /**
     * Returns write buffer.
     *
     * @return Buffer
     */
    protected function getWriteBuffer()
    {
        return $this->writeBuffer;
    }

    /**
     * Enqueues given frame for later processing.
     *
     * @param AbstractFrame $frame
     */
    protected function enqueue(AbstractFrame $frame)
    {
        $this->queue[] = $frame;
    }

    /**
     * Creates stream according to options passed in constructor.
     *
     * @return resource
     */
    protected function getStream()
    {
        if ($this->stream === null) {
            // TODO: SSL

            // see https://github.com/nrk/predis/blob/v1.0/src/Connection/StreamConnection.php
            $uri = "tcp://{$this->options["host"]}:{$this->options["port"]}";
            $flags = STREAM_CLIENT_CONNECT;

            if (isset($this->options["async_connect"]) && !!$this->options["async_connect"]) {
                $flags |= STREAM_CLIENT_ASYNC_CONNECT;
            }

            if (isset($this->options["persistent"]) && !!$this->options["persistent"]) {
                $flags |= STREAM_CLIENT_PERSISTENT;

                if (!isset($this->options["path"])) {
                    throw new ClientException("If you need persistent connection, you have to specify 'path' option.");
                }

                $uri .= (strpos($this->options["path"], "/") === 0) ? $this->options["path"] : "/" . $this->options["path"];
            }

            $this->stream = @stream_socket_client($uri, $errno, $errstr, (float)$this->options["timeout"], $flags);

            if (!$this->stream) {
                throw new ClientException(
                    "Could not connect to {$this->options["host"]}:{$this->options["port"]}: {$errstr}.",
                    $errno
                );
            }

            if (isset($this->options["read_write_timeout"])) {
                $readWriteTimeout = (float)$this->options["read_write_timeout"];
                if ($readWriteTimeout < 0) {
                    $readWriteTimeout = -1;
                }
                $readWriteTimeoutSeconds = floor($readWriteTimeout);
                $readWriteTimeoutMicroseconds = ($readWriteTimeout - $readWriteTimeoutSeconds) * 10e6;
                stream_set_timeout($this->stream, $readWriteTimeoutSeconds, $readWriteTimeoutMicroseconds);
            }

            if (isset($this->options["tcp_nodelay"]) && function_exists("socket_import_stream")) {
                $socket = socket_import_stream($this->stream);
                socket_set_option($socket, SOL_TCP, TCP_NODELAY, (int)$this->options["tcp_nodelay"]);
            }

            if ($this->options["async"]) {
                stream_set_blocking($this->stream, 0);
            }
        }

        return $this->stream;
    }

    /**
     * Closes stream.
     */
    protected function closeStream()
    {
        @fclose($this->stream);
        $this->stream = null;
    }

    /**
     * Reads data from stream into {@link readBuffer}.
     */
    protected function read()
    {
        $s = @fread($this->stream, $this->frameMax);

        if ($s === false) {
            $info = stream_get_meta_data($this->stream);

            if (isset($info["timed_out"]) && $info["timed_out"]) {
                throw new ClientException("Timeout reached while reading from stream.");
            }
        }

        if (@feof($this->stream)) {
            throw new ClientException("Broken pipe or closed connection.");
        }

        $this->readBuffer->append($s);
        $this->lastRead = microtime(true);
    }

    /**
     * Writes data from {@link writeBuffer} to stream.
     */
    protected function write()
    {
        if (($written = @fwrite($this->getStream(), $this->writeBuffer->read($this->writeBuffer->getLength()))) === false) {
            throw new ClientException("Could not write data to socket.");
        }

        if ($written === 0) {
            throw new ClientException("Broken pipe or closed connection.");
        }

        fflush($this->getStream()); // flush internal PHP buffers

        $this->writeBuffer->discard($written);
        $this->lastWrite = microtime(true);
    }

    /**
     * Responds to authentication challenge
     *
     * @param MethodConnectionStartFrame $start
     * @return boolean|Promise\PromiseInterface
     */
    protected function authResponse(MethodConnectionStartFrame $start)
    {
        if (strpos($start->mechanisms, "AMQPLAIN") === false) {
            throw new ClientException("Server does not support AMQPLAIN mechanism (supported: {$start->mechanisms}).");
        }

        $responseBuffer = new Buffer();
        $this->writer->appendTable([
            "LOGIN" => $this->options["user"],
            "PASSWORD" => $this->options["password"],
        ], $responseBuffer);
        $responseBuffer->discard(4);

        return $this->connectionStartOk([], "AMQPLAIN", $responseBuffer->read($responseBuffer->getLength()), "en_US");
    }

    /**
     * Disconnects the client.
     *
     * Always returns a promise (even sync client)
     *
     * @param int $replyCode
     * @param string $replyText
     * @return Promise\PromiseInterface
     */
    abstract public function disconnect($replyCode = 0, $replyText = "");

    /**
     * Returns true if client is connected to server.
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->state !== ClientStateEnum::NOT_CONNECTED && $this->state !== ClientStateEnum::ERROR;
    }

    /**
     * Returns current client state.
     *
     * @return int
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Creates and opens new channel.
     *
     * Channel gets first available channel id.
     *
     * @return Channel|Promise\PromiseInterface
     */
    public function channel()
    {
        // TODO: improve next channel id selection
        for ($channelId = 1; isset($this->channels[$channelId]); ++$channelId) ;
        $this->channels[$channelId] = new Channel($this, $channelId);
        $response = $this->channelOpen($channelId);

        if ($response instanceof MethodChannelOpenOkFrame) {
            return $this->channels[$channelId];

        } elseif ($response instanceof Promise\PromiseInterface) {
            return $response->then(function () use ($channelId) {
                return $this->channels[$channelId];
            });

        } else {
            $this->state = ClientStateEnum::ERROR;

            throw new ClientException(
                "channel.open unexpected response of type " . gettype($response) .
                (is_object($response) ? "(" . get_class($response) . ")" : "") .
                "."
            );
        }
    }

    /**
     * Removes channel.
     *
     * @param int $channelId
     * @return void
     */
    public function removeChannel($channelId)
    {
        unset($this->channels[$channelId]);
    }

    /**
     * Callback after connection-level frame has been received.
     *
     * @param AbstractFrame $frame
     */
    public function onFrameReceived(AbstractFrame $frame)
    {
        if ($frame instanceof MethodFrame) {
            if ($frame instanceof MethodConnectionCloseFrame) {
                throw new ClientException("Connection closed by server: " . $frame->replyText, $frame->replyCode);
            } else {
                throw new ClientException("Unhandled method frame " . get_class($frame) . ".");
            }

        } elseif ($frame instanceof ContentHeaderFrame) {
            $this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got header frame on connection channel (#0).");

        } elseif ($frame instanceof ContentBodyFrame) {
            $this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got body frame on connection channel (#0).");

        } elseif ($frame instanceof HeartbeatFrame) {
            $this->lastRead = microtime(true);

        } else {
            throw new ClientException("Unhandled frame " . get_class($frame) . ".");
        }
    }

    /**
     * @return int
     */
    protected function getFrameMax()
    {
        return $this->frameMax;
    }

    /**
     * Wait for messages on connection and process them. Will process messages for at most $maxSeconds.
     *
     * @param float $maxSeconds
     * @return void
     */
    abstract public function run($maxSeconds = null);

}
