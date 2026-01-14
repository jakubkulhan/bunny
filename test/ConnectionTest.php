<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Channels;
use Bunny\Configuration;
use Bunny\Connection;
use Bunny\Constants;
use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\MethodConnectionCloseFrame;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;
use Bunny\Test\Library\ClientHelper;
use Evenement\EventEmitterTrait;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface;
use RuntimeException;
use Throwable;
use WyriHaximus\React\PHPUnit\RunTestsInFibersTrait;
use function React\Async\async;
use function React\Async\await;

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

        // phpcs:disable
        $mockConnection = new class () implements ConnectionInterface {
            use EventEmitterTrait;

            /**
             * @return string
             */
            public function getRemoteAddress()
            {
                return '127.0.0.1:666';
            }

            /**
             * @return string
             */
            public function getLocalAddress()
            {
                return '127.0.0.1:666';
            }

            public function isReadable()
            {
                return true;
            }

            public function pause()
            {
                // No-op
            }

            public function resume()
            {
                // No-op
            }

            /**
             * @param array<mixed> $options
             */
            public function pipe(WritableStreamInterface $dest, array $options = [])
            {
                return new ThroughStream();
            }

            public function close()
            {
                // No-op
            }

            /**
             * @retrun bool
             */
            public function isWritable()
            {
                return true;
            }

            /**
             * @param string $data
             *
             * @retrun bool
             */
            public function write($data)
            {
                return false;
            }

            /**
             * @param ?string $data
             */
            public function end($data = null)
            {
                // No-op
            }
        };
        // phpcs:enable
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
}
