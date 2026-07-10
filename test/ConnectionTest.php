<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Channels;
use Bunny\ClientInterface;
use Bunny\Configuration;
use Bunny\Connection;
use Bunny\Constants;
use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\MethodConnectionCloseFrame;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;
use Bunny\Test\Library\ClientFactory;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use React\Promise\Deferred;
use RuntimeException;
use Throwable;
use function React\Async\async;
use function React\Async\await;
use function base64_decode;
use function substr_count;

final class ConnectionTest extends TestCase
{
    public function testThrowOn(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        self::expectException(ClientException::class);
        self::expectExceptionMessage('blaat');

        $socketConnection = new MockConnectionInterface();
        $buffer = new Buffer();
        $frame = new MethodConnectionCloseFrame();
        $frame->replyCode = Constants::STATUS_REPLY_SUCCESS;
        $frame->replyText = 'blaat';
        $frame->closeClassId = Constants::CLASS_CONNECTION;
        $frame->closeMethodId = Constants::METHOD_CONNECTION_CLOSE;
        (new ProtocolWriter())->appendFrame($frame, $buffer);
        $connection = new Connection(
            ClientFactory::createClient(),
            $socketConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );
        $deferred = new Deferred();
        Loop::addTimer(0.1, async(static function () use ($deferred, $connection): void {
            try {
                $connection->awaitAck(666);
                $deferred->reject(new RuntimeException('We should not reach this line'));
            } catch (Throwable $exception) {
                $deferred->reject($exception);
            }
        }));
        Loop::addTimer(0.2, async(static function () use ($socketConnection, $buffer): void {
            $line = $buffer->consume($buffer->getLength());
            $socketConnection->emit('data', [$line]);
        }));
        Loop::addTimer(0.3, async(static function () use ($socketConnection): void {
            $socketConnection->emit('drain');
        }));
        Loop::addTimer(1, async(static function (): void {
            Loop::stop();
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());
    }

    public function testNoMethodConnectionCloseOkFrameIsWrittenWhenConnectionIsCloseWhenDisconnectionWithActiveConnection(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        $socketConnection = new MockConnectionInterface();
        $connection = new Connection(
            ClientFactory::createClient(),
            $socketConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );
        $baseBuffer = $socketConnection->getWrittenData();
        self::assertSame('', $baseBuffer);
        $deferred = new Deferred();
        Loop::addTimer(0.1, async(static function () use ($deferred, $connection): void {
            try {
                $connection->disconnect(0, '');
            } catch (Throwable $exception) {
                $deferred->reject($exception);
            }
        }));
        Loop::addTimer(0.3, async(static function () use ($socketConnection): void {
            $socketConnection->emit('drain');
        }));
        Loop::addTimer(1, async(static function () use ($deferred): void {
            Loop::stop();
            $deferred->resolve(null);
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());

        $afterCloseBuffer = $socketConnection->getWrittenData();

        self::assertSame(base64_decode('AQAAAAAACwAKADIAAAAAAAAAzg=='), $afterCloseBuffer);
    }

    public function testNoMethodConnectionCloseOkFrameIsWrittenWhenConnectionIsCloseWhenDisconnectionWithInactiveConnection(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        $socketConnection = new MockConnectionInterface();
        $connection = new Connection(
            ClientFactory::createClient(),
            $socketConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );
        $baseBuffer = $socketConnection->getWrittenData();
        self::assertSame('', $baseBuffer);
        $deferred = new Deferred();
        Loop::addTimer(0.1, async(static function () use ($deferred, $connection): void {
            try {
                $connection->disconnect(0, '', ClientInterface::RAW_CONNECTION_INACTIVE);
            } catch (Throwable $exception) {
                $deferred->reject($exception);
            }
        }));
        Loop::addTimer(0.3, async(static function () use ($socketConnection): void {
            $socketConnection->emit('drain');
        }));
        Loop::addTimer(1, async(static function () use ($deferred): void {
            Loop::stop();
            $deferred->resolve(null);
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());

        $afterCloseBuffer = $socketConnection->getWrittenData();

        self::assertSame($baseBuffer, $afterCloseBuffer);
    }

    public function testConnectingEmittingCloseWillResultInFastClosure(): void
    {
        $client = new MockClientInterface();
        $socketConnection = new MockConnectionInterface();
        new Connection(
            $client,
            $socketConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );

        $socketConnection->emit('close');

        self::assertSame(0, $client->getIsConnectedCount());
        self::assertSame(1, $client->getCanDisconnectCount());
        self::assertSame(1, $client->getDisconnectCount());
    }

    public function testConnectingEmittingCloseWillResultInFastClosureButNotWhenNotConnected(): void
    {
        $client = new MockClientInterface(isConnected: false, canDisconnect:  false);
        $socketConnection = new MockConnectionInterface();
        new Connection(
            $client,
            $socketConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );

        $socketConnection->emit('close');

        self::assertSame(0, $client->getIsConnectedCount());
        self::assertSame(1, $client->getCanDisconnectCount());
        self::assertSame(0, $client->getDisconnectCount());
    }

    public function testDisconnectCancelsHeartbeatTimer(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        $socketConnection = new MockConnectionInterface();
        $connection = new Connection(
            new MockClientInterface(),
            $socketConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(heartbeat: 0.1),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );

        $deferred = new Deferred();
        Loop::addTimer(0.01, async(static function () use ($connection): void {
            $connection->appendProtocolHeader();
            $connection->flushWriteBuffer();
            $connection->startHeartbeatTimer();
        }));
        Loop::addTimer(0.05, async(static function () use ($connection): void {
            $connection->disconnect(0, '', ClientInterface::RAW_CONNECTION_INACTIVE);
        }));
        Loop::addTimer(0.3, async(static function () use ($deferred): void {
            Loop::stop();
            $deferred->resolve(null);
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());

        $heartbeatFrame = "\x08\x00\x00\x00\x00\x00\x00\xCE";
        self::assertStringNotContainsString($heartbeatFrame, $socketConnection->getWrittenData());
    }

    public function testStartHeartbeatTimerCancelsExistingTimerSoDisconnectCancelsAll(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        $socketConnection = new MockConnectionInterface();
        $connection = new Connection(
            new MockClientInterface(),
            $socketConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(heartbeat: 0.1),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );

        $deferred = new Deferred();
        Loop::addTimer(0.01, async(static function () use ($connection): void {
            $connection->appendProtocolHeader();
            $connection->flushWriteBuffer();
            $connection->startHeartbeatTimer();
            $connection->startHeartbeatTimer();
        }));
        Loop::addTimer(0.05, async(static function () use ($connection): void {
            $connection->disconnect(0, '', ClientInterface::RAW_CONNECTION_INACTIVE);
        }));
        Loop::addTimer(0.3, async(static function () use ($deferred): void {
            Loop::stop();
            $deferred->resolve(null);
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());

        $heartbeatFrame = "\x08\x00\x00\x00\x00\x00\x00\xCE";
        self::assertStringNotContainsString($heartbeatFrame, $socketConnection->getWrittenData());
    }

    public function testDrainEventDuringPendingHeartbeatTimerDoesNotLeakTimer(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        $socketConnection = new MockConnectionInterface();
        $connection = new Connection(
            new MockClientInterface(),
            $socketConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(heartbeat: 0.1),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );

        $deferred = new Deferred();
        Loop::addTimer(0.01, async(static function () use ($connection): void {
            $connection->appendProtocolHeader();
            $connection->flushWriteBuffer();
            $connection->startHeartbeatTimer();
        }));
        Loop::addTimer(0.05, async(static function () use ($socketConnection): void {
            $socketConnection->emit('drain');
        }));
        Loop::addTimer(0.06, async(static function () use ($connection): void {
            $connection->disconnect(0, '', ClientInterface::RAW_CONNECTION_INACTIVE);
        }));
        Loop::addTimer(0.3, async(static function () use ($deferred): void {
            Loop::stop();
            $deferred->resolve(null);
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());

        $heartbeatFrame = "\x08\x00\x00\x00\x00\x00\x00\xCE";
        self::assertStringNotContainsString($heartbeatFrame, $socketConnection->getWrittenData());
    }

    public function testHeartbeatTimerFiresExactlyOnceWhenStartedTwice(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        $mockConnection = new MockConnectionInterface();
        $connection = new Connection(
            new MockClientInterface(),
            $mockConnection,
            new Buffer(),
            new Buffer(),
            new ProtocolReader(),
            new ProtocolWriter(),
            new Channels(),
            new Configuration(heartbeat: 0.15),
            static function (): int {
                return Constants::FRAME_MAX;
            },
        );

        $deferred = new Deferred();
        Loop::addTimer(0.01, async(static function () use ($connection): void {
            $connection->appendProtocolHeader();
            $connection->flushWriteBuffer();
            $connection->startHeartbeatTimer();
            $connection->startHeartbeatTimer();
        }));
        Loop::addTimer(0.28, async(static function () use ($deferred): void {
            Loop::stop();
            $deferred->resolve(null);
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());

        $heartbeatFrame = "\x08\x00\x00\x00\x00\x00\x00\xCE";
        $heartbeatCount = substr_count($mockConnection->getWrittenData(), $heartbeatFrame);
        self::assertSame(1, $heartbeatCount);
    }
}
