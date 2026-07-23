<?php

declare(strict_types=1);

namespace Bunny;

use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\MethodConnectionStartFrame;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use InvalidArgumentException;
use React\Promise\Deferred;
use Throwable;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function is_array;
use function is_callable;
use function sprintf;
use function strpos;

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
class Client implements ClientInterface, EventEmitterInterface
{
    use EventEmitterTrait;

    private readonly Configuration $configuration;

    private ClientState $state = ClientState::NotConnected;

    private ?Connection $connection = null;

    private Channels $channels;

    public int $frameMax = Constants::FRAME_MAX;

    private int $nextChannelId = 1;

    private int $channelMax = Constants::CHANNEL_MAX;

    /**
     * @var list<\React\Promise\Deferred<null>>
     */
    private array $connectQueue = [];

    /**
     * @param Configuration|array<string, mixed> $configuration
     */
    public function __construct(Configuration|array $configuration = [])
    {
        if (is_array($configuration)) {
            if (!isset($configuration['host'])) {
                $configuration['host'] = Defaults::HOST;
            }

            if (!isset($configuration['port'])) {
                $configuration['port'] = Defaults::PORT;
            }

            if (!isset($configuration['vhost'])) {
                if (isset($configuration['virtual_host'])) {
                    $configuration['vhost'] = $configuration['virtual_host'];
                    unset($configuration['virtual_host']);
                } elseif (isset($configuration['path'])) {
                    $configuration['vhost'] = $configuration['path'];
                    unset($configuration['path']);
                } else {
                    $configuration['vhost'] = Defaults::VHOST;
                }
            }

            if (!isset($configuration['user'])) {
                if (isset($configuration['username'])) {
                    $configuration['user'] = $configuration['username'];
                    unset($configuration['username']);
                } else {
                    $configuration['user'] = Defaults::USER;
                }
            }

            if (!isset($configuration['password'])) {
                if (isset($configuration['pass'])) {
                    $configuration['password'] = $configuration['pass'];
                    unset($configuration['pass']);
                } else {
                    $configuration['password'] = Defaults::PASSWORD;
                }
            }

            if (!isset($configuration['timeout'])) {
                $configuration['timeout'] = Defaults::TIMEOUT;
            }

            if (!isset($configuration['heartbeat'])) {
                $configuration['heartbeat'] = Defaults::HEARTBEAT;
            } elseif ($configuration['heartbeat'] >= 2 ** 15) {
                throw new InvalidArgumentException('Heartbeat too high: the value is a signed int16.');
            }

            if (!is_callable($configuration['heartbeat_callback'] ?? null)) {
                unset($configuration['heartbeat_callback']);
            }

            if (isset($configuration['ssl']) && is_array($configuration['ssl'])) {
                $configuration['tls'] = $configuration['ssl'];
            }

            if (!isset($configuration['client_properties'])) {
                $configuration['client_properties'] = Defaults::CLIENT_PROPERTIES;
            }

            if (!is_array($configuration['client_properties'])) {
                throw new InvalidArgumentException('Client properties must be an array');
            }

            $configuration = new Configuration(
                host: $configuration['host'],
                port: $configuration['port'],
                vhost: $configuration['vhost'],
                user: $configuration['user'],
                password: $configuration['password'],
                timeout: $configuration['timeout'],
                heartbeat: $configuration['heartbeat'],
                heartbeatCallback: $configuration['heartbeat_callback'] ?? null,
                tls: $configuration['tls'] ?? Defaults::TLS,
                clientProperties: $configuration['client_properties'],
            );
        }

        $this->configuration = $configuration;
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

        if ($this->state === ClientState::Connecting) {
            $this->awaitConnection();
        }

        $channelId = $this->findChannelId();

        $channel = new Channel($this->connection, $this, $channelId);
        $channel->on('error', function (Throwable $error): void {
            $this->emit('error', [$error]);
        });
        $channel->once('close', function () use ($channelId): void {
            $this->channels->unset($channelId);
        });
        $this->channels->set($channelId, $channel);
        $this->connection->channelOpen($channelId);

        return $channel;
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

        try {
            $this->connection = new Connection(
                $this,
                await($this->configuration->connector->connect($this->configuration->uri)),
                new Buffer(),
                new Buffer(),
                new ProtocolReader(),
                new ProtocolWriter(),
                $this->channels,
                $this->configuration,
                function (): int {
                    return $this->frameMax;
                },
            );
            $this->connection->on('error', function (Throwable $error): void {
                $this->emit('error', [$error]);
            });
            $this->connection->appendProtocolHeader();
            $this->connection->flushWriteBuffer();
            $start = $this->connection->awaitConnectionStart();
            $this->authResponse($start);
            $tune = $this->connection->awaitConnectionTune();
            $this->frameMax = $tune->frameMax;
            if ($tune->channelMax > 0) {
                $this->channelMax = $tune->channelMax;
            }

            $this->connection->connectionTuneOk($tune->channelMax, $tune->frameMax, (int) $this->configuration->heartbeat);
            $this->connection->connectionOpen($this->configuration->vhost);
            $this->connection->startHeartbeatTimer();

            $this->state = ClientState::Connected;
        } catch (Throwable $thrown) {
            $exception = new ClientException('Could not connect to ' . $this->configuration->uri . ': ' . $thrown->getMessage(), $thrown->getCode(), $thrown);

            $this->resolveConnectQueue($exception);

            throw $exception;
        }

        $this->resolveConnectQueue();

        return $this;
    }

    private function awaitConnection(): void
    {
        $deferred = new Deferred();

        $this->connectQueue[] = $deferred;

        await($deferred->promise());
    }

    private function resolveConnectQueue(?Throwable $exception = null): void
    {
        foreach ($this->connectQueue as $channelPromise) {
            if ($exception !== null) {
                $channelPromise->reject($exception);
            } else {
                $channelPromise->resolve(null);
            }
        }

        $this->connectQueue = [];
    }

    /**
     * Responds to authentication challenge
     */
    protected function authResponse(MethodConnectionStartFrame $start): void
    {
        if (strpos($start->mechanisms, 'AMQPLAIN') === false) {
            throw new ClientException(sprintf('Server does not support AMQPLAIN mechanism (supported: %s).', $start->mechanisms));
        }

        $responseBuffer = new Buffer();
        (new ProtocolWriter())->appendTable([
            'LOGIN' => $this->configuration->user,
            'PASSWORD' => $this->configuration->password,
        ], $responseBuffer);
        $responseBuffer->discard(4);

        $this->connection->connectionStartOk($responseBuffer->read($responseBuffer->getLength()), $this->configuration->clientProperties, 'AMQPLAIN', 'en_US');
    }

    /**
     * Disconnects the client.
     */
    public function disconnect(int $replyCode = 0, string $replyText = '', bool $connectionStatus = ClientInterface::RAW_CONNECTION_ACTIVE): void
    {
        if ($this->state === ClientState::Disconnecting) {
            return;
        }

        if ($this->state !== ClientState::Connected) {
            throw new ClientException('Client is not connected.');
        }

        $this->state = ClientState::Disconnecting;

        $this->emit('close');

        $promises = [];
        foreach ($this->channels->all() as $channelId => $channel) {
            $promises[] = async(static fn () => $channel->close($replyCode, $replyText, $connectionStatus))();
        }

        await(all($promises));

        $this->connection->disconnect($replyCode, $replyText, $connectionStatus);

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
     * Returns true if client can be disconnected.
     */
    public function canDisconnect(): bool
    {
        return $this->state === ClientState::Connected;
    }

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
