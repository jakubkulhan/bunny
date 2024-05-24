<?php

declare(strict_types=1);

namespace Bunny;

use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\MethodChannelOpenOkFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;
use React\Socket\Connector;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

/**
 * Synchronous AMQP/RabbitMQ client.
 *
 * The client's API follows AMQP class/method naming convention and uses PHP's idiomatic camelCase method naming
 * convention - e.g. 'queue.declare' has corresponding method 'queueDeclare', 'exchange.delete' -> 'exchangeDelete'.
 * Methods from 'basic' class are not prefixed with 'basic' - e.g. 'basic.publish' is just 'publish'.
 *
 * Usage:
 *
 *     $c = new Bunny\Client([
 *         'host' => '127.0.0.1',
 *         'port' => 5672,
 *         'vhost' => '/',
 *         'user' => 'guest',
 *         'password' => 'guest',
 *     ]);
 *
 *     // client is lazy and will connect once you open a channel, e.g. $c->channel()
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 * @final Will be marked final in a future major release
 */
class Client implements ClientInterface
{
    private readonly array $options;

    private readonly Connector $connector;
    
    private ClientState $state = ClientState::NotConnected;

    private ?Connection $connection = null;

    private Channels $channels;

    /** @var int */
    public int $frameMax = 0xFFFF;

    /** @var int  */
    private int $nextChannelId = 1;

    /** @var int  */
    private int $channelMax = 0xFFFF;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if (!isset($options['host'])) {
            $options['host'] = '127.0.0.1';
        }

        if (!isset($options['port'])) {
            $options['port'] = 5672;
        }

        if (!isset($options['vhost'])) {
            if (isset($options['virtual_host'])) {
                $options['vhost'] = $options['virtual_host'];
                unset($options['virtual_host']);
            } elseif (isset($options['path'])) {
                $options['vhost'] = $options['path'];
                unset($options['path']);
            } else {
                $options['vhost'] = '/';
            }
        }

        if (!isset($options['user'])) {
            if (isset($options['username'])) {
                $options['user'] = $options['username'];
                unset($options['username']);
            } else {
                $options['user'] = 'guest';
            }
        }

        if (!isset($options['password'])) {
            if (isset($options['pass'])) {
                $options['password'] = $options['pass'];
                unset($options['pass']);
            } else {
                $options['password'] = 'guest';
            }
        }

        if (!isset($options['timeout'])) {
            $options['timeout'] = 1;
        }

        if (!isset($options['heartbeat'])) {
            $options['heartbeat'] = 60.0;
        } elseif ($options['heartbeat'] >= 2**15) {
            throw new \InvalidArgumentException('Heartbeat too high: the value is a signed int16.');
        }

        if (!(is_callable($options['heartbeat_callback'] ?? null))) {
            unset($options['heartbeat_callback']);
        }

        if (isset($options['ssl']) && is_array($options['ssl'])) {
            $options['tls'] = $options['ssl'];
        }

        if (!isset($options['client_properties'])) {
            $options['client_properties'] = [];
        }

        if (!is_array($options['client_properties'])) {
            throw new \InvalidArgumentException('Client properties must be an array');
        }

        $this->options = $options;
        $this->connector = new Connector($this->options);


        $this->state = ClientState::NotConnected;
        $this->channels = new Channels();
    }

    /**
     * Creates and opens new channel.
     *
     * Channel gets first available channel id.
     */
    public function channel(): ChannelInterface
    {

        if (!$this->isConnected()) {
            $this->connect();
        }

        $channelId = $this->findChannelId();

        $channel = new Channel($this->connection, $this, $channelId);
        $channel->once('close', function () use ($channelId) {
            $this->channels->unset($channelId);
        });
        $this->channels->set($channelId, $channel);
        $response = $this->connection->channelOpen($channelId);

        if ($response instanceof MethodChannelOpenOkFrame) {
            return $channel;
        }

        $this->state = ClientState::Error;

        throw new ClientException(
            'channel.open unexpected response of type ' . gettype($response) . '.'
        );
    }

    /**
     * Connects to AMQP server.
     *
     * Calling connect() multiple times will result in error.
     */
    public function connect(): self
    {
        if ($this->state !== ClientState::NotConnected) {
            throw new ClientException('Client already connected/connecting.');
        }

        $this->state = ClientState::Connecting;

        $streamScheme = 'tcp';
        if (isset($this->options['tls']) && is_array($this->options['tls'])) {
            $streamScheme = 'tls';
        }
        $uri = $streamScheme . "://{$this->options['host']}:{$this->options['port']}";

        try {
            $this->connection = new Connection(
                $this,
                await($this->connector->connect($uri)),
                new Buffer(),
                new Buffer(),
                new ProtocolReader(),
                new ProtocolWriter(),
                $this->channels,
                $this->options,
            );
            $this->connection->appendProtocolHeader();
            $this->connection->flushWriteBuffer();
            $start = $this->connection->awaitConnectionStart();
            $this->authResponse($start);
            $tune = $this->connection->awaitConnectionTune();
            $this->frameMax = $tune->frameMax;
            if ($tune->channelMax > 0) {
                $this->channelMax = $tune->channelMax;
            }
            $this->connection->connectionTuneOk($tune->channelMax, $tune->frameMax, (int)$this->options['heartbeat']);
            $this->connection->connectionOpen($this->options['vhost']);
            $this->connection->startHeartbeatTimer();

            $this->state = ClientState::Connected;
        } catch (\Throwable $thrown) {
            throw new ClientException('Could not connect to ' . $uri . ': ' . $thrown->getMessage(), $thrown->getCode(), $thrown);
        }

        return $this;
    }

    /**
     * Responds to authentication challenge
     *
     * @param MethodConnectionStartFrame $start
     */
    protected function authResponse(MethodConnectionStartFrame $start): void
    {
        if (strpos($start->mechanisms, 'AMQPLAIN') === false) {
            throw new ClientException('Server does not support AMQPLAIN mechanism (supported: {$start->mechanisms}).');
        }

        $responseBuffer = new Buffer();
        (new ProtocolWriter())->appendTable([
            'LOGIN' => $this->options['user'],
            'PASSWORD' => $this->options['password'],
        ], $responseBuffer);
        $responseBuffer->discard(4);

        $this->connection->connectionStartOk($responseBuffer->read($responseBuffer->getLength()), $this->options['client_properties'], 'AMQPLAIN', 'en_US');
    }

    /**
     * Disconnects the client.
     */
    public function disconnect(int $replyCode = 0, string $replyText = ''): void
    {
        if ($this->state === ClientState::Disconnecting) {
            return;
        }

        if ($this->state !== ClientState::Connected) {
            throw new ClientException('Client is not connected.');
        }

        $this->state = ClientState::Disconnecting;

        $promises = [];
        foreach ($this->channels->all() as $channelId => $channel) {
            $promises[] = async(static function () use ($channel, $replyCode, $replyText): void {
                $channel->close($replyCode, $replyText);
            })();
        }
        await(all($promises));

        $this->connection->disconnect($replyCode, $replyText);

        $this->state = ClientState::NotConnected;
    }

    /**
     * Returns true if client is connected to server.
     */
    public function isConnected(): bool
    {
        return $this->state !== ClientState::NotConnected && $this->state !== ClientState::Error;
    }

    /**
     * @return int
     */
    private function findChannelId(): int
    {
        // first check in range [next, max] ...
        for (
            $channelId = $this->nextChannelId;
            $channelId <= $this->channelMax;
            ++$channelId
        ) {
            if (!$this->channels->has($channelId)) {
                $this->nextChannelId = $channelId + 1;

                return $channelId;
            }
        }

        // then check in range [min, next) ...
        for (
            $channelId = 1;
            $channelId < $this->nextChannelId;
            ++$channelId
        ) {
            if (!$this->channels->has($channelId)) {
                $this->nextChannelId = $channelId + 1;

                return $channelId;
            }
        }

        throw new ClientException('No available channels');
    }
}
