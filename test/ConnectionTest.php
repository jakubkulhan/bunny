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
use Bunny\Test\Library\ClientHelper;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use React\Promise\Deferred;
use RuntimeException;
use Throwable;
use WyriHaximus\React\PHPUnit\RunTestsInFibersTrait;
use function React\Async\async;
use function React\Async\await;
use function base64_decode;

class ConnectionTest extends TestCase
{
    use RunTestsInFibersTrait;

    private ClientHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new ClientHelper();
    }

    public function testThrowOn(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        self::expectException(ClientException::class);
        self::expectExceptionMessage('blaat');

        $mockConnection = new MockConnectionInterface();
        $buffer = new Buffer();
        $frame = new MethodConnectionCloseFrame();
        $frame->replyCode = Constants::STATUS_REPLY_SUCCESS;
        $frame->replyText = 'blaat';
        $frame->closeClassId = Constants::CLASS_CONNECTION;
        $frame->closeMethodId = Constants::METHOD_CONNECTION_CLOSE;
        (new ProtocolWriter())->appendFrame($frame, $buffer);
        $connection = new Connection(
            $this->helper->createClient(),
            $mockConnection,
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
        Loop::addTimer(0.2, async(static function () use ($mockConnection, $buffer): void {
            $line = $buffer->consume($buffer->getLength());
            $mockConnection->emit('data', [$line]);
        }));
        Loop::addTimer(0.3, async(static function () use ($mockConnection): void {
            $mockConnection->emit('drain');
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

        $mockConnection = new MockConnectionInterface();
        $connection = new Connection(
            $this->helper->createClient(),
            $mockConnection,
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
        $baseBuffer = $mockConnection->getWrittenData();
        self::assertSame('', $baseBuffer);
        $deferred = new Deferred();
        Loop::addTimer(0.1, async(static function () use ($deferred, $connection): void {
            try {
                $connection->disconnect(0, '');
            } catch (Throwable $exception) {
                $deferred->reject($exception);
            }
        }));
        Loop::addTimer(0.3, async(static function () use ($mockConnection): void {
            $mockConnection->emit('drain');
        }));
        Loop::addTimer(1, async(static function () use ($deferred): void {
            Loop::stop();
            $deferred->resolve(null);
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());

        $afterCloseBuffer = $mockConnection->getWrittenData();

        self::assertSame(base64_decode('AQAAAAAACwAKADIAAAAAAAAAzg=='), $afterCloseBuffer);
    }

    public function testNoMethodConnectionCloseOkFrameIsWrittenWhenConnectionIsCloseWhenDisconnectionWithInactiveConnection(): void
    {
        $oldLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        $mockConnection = new MockConnectionInterface();
        $connection = new Connection(
            $this->helper->createClient(),
            $mockConnection,
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
        $baseBuffer = $mockConnection->getWrittenData();
        self::assertSame('', $baseBuffer);
        $deferred = new Deferred();
        Loop::addTimer(0.1, async(static function () use ($deferred, $connection): void {
            try {
                $connection->disconnect(0, '', ClientInterface::RAW_CONNECTION_INACTIVE);
            } catch (Throwable $exception) {
                $deferred->reject($exception);
            }
        }));
        Loop::addTimer(0.3, async(static function () use ($mockConnection): void {
            $mockConnection->emit('drain');
        }));
        Loop::addTimer(1, async(static function () use ($deferred): void {
            Loop::stop();
            $deferred->resolve(null);
        }));

        Loop::run();
        Loop::set($oldLoop);

        await($deferred->promise());

        $afterCloseBuffer = $mockConnection->getWrittenData();

        self::assertSame($baseBuffer, $afterCloseBuffer);
    }

    public function testConnectingEmittingCloseWillResultInFastClosure(): void
    {
        $client = new MockClientInterface(true, true);
        $mockConnection = new MockConnectionInterface();
        new Connection(
            $client,
            $mockConnection,
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

        $mockConnection->emit('close');

        self::assertSame(0, $client->getIsConnectedCount());
        self::assertSame(1, $client->getCanDisconnectCount());
        self::assertSame(1, $client->getDisconnectCount());
    }

    public function testConnectingEmittingCloseWillResultInFastClosureButNotWhenNotConnected(): void
    {
        $client = new MockClientInterface(false, false);
        $mockConnection = new MockConnectionInterface();
        new Connection(
            $client,
            $mockConnection,
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

        $mockConnection->emit('close');

        self::assertSame(0, $client->getIsConnectedCount());
        self::assertSame(1, $client->getCanDisconnectCount());
        self::assertSame(0, $client->getDisconnectCount());
    }
}
