<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Constants;
use Bunny\Exception\ChannelException;
use Bunny\Message;
use Bunny\Protocol\AbstractFrame;
use Bunny\Test\Library\ClientHelper;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use Throwable;
use WyriHaximus\React\PHPUnit\RunTestsInFibersTrait;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;
use function spl_object_id;
use function str_repeat;

class ChannelTest extends TestCase
{
    use RunTestsInFibersTrait;

    private ClientHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new ClientHelper();
    }

    public static function tearDownAfterClass(): void
    {
        Loop::stop();
    }

    public function testClose(): void
    {
        $this->expectNotToPerformAssertions();

        $c = $this->helper->createClient();
        $c->connect();
        $c->channel()->close();
    }

    public function testExchangeDeclare(): void
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->exchangeDeclare('test_exchange', 'direct', false, false, true);
        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testQueueDeclare(): void
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', false, false, false, true);
        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testQueueBind(): void
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->exchangeDeclare('test_exchange', 'direct', false, false, true);
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', false, false, false, true);
        self::assertTrue($c->isConnected());
        $ch->queueBind('test_exchange', 'test_queue');
        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testPublish(): void
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->publish('test publish', []);
        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testConsume(): void
    {
        /**
         * @var Deferred<string> $deferred
         */
        $deferred = new Deferred();
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', false, false, false, true);
        self::assertTrue($c->isConnected());
        $ch->consume(static function (Message $msg, Channel $ch, Client $c) use ($deferred): void {
            $deferred->resolve($msg->content);
        }, 'test_queue');
        self::assertTrue($c->isConnected());
        $ch->publish('hi', [], '', 'test_queue');
        self::assertEquals('hi', await($deferred->promise()));

        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    /**
     * @return iterable<array<int>>
     */
    public static function invalidConsumeConcurrencyProvider(): iterable
    {
        yield [0];
        yield [-1];
    }

    /**
     * @param positive-int $concurrency; This is a ly to stop PHPStan from complaining this is an int
     *
     * @dataProvider invalidConsumeConcurrencyProvider
     */
    public function testConsumeWithInvalidConcurrency(int $concurrency): void
    {
        self::expectException(ChannelException::class);
        self::expectExceptionMessage('basic.consume concurrency must be 1 or higher');

        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', false, false, false, true);
        self::assertTrue($c->isConnected());
        $ch->consume(static function (Message $msg, Channel $ch, Client $c): void {
        }, concurrency: $concurrency);
        self::assertTrue($c->isConnected());
        $ch->publish('hi', [], '', 'test_queue');

        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testConsumeWithConcurrencyDoesntConsumeMoreMessagesConcurrentlyThanConfigured(): void
    {
        /**
         * @var Deferred<int> $deferred
         */
        $deferred = new Deferred();
        $count = 0;
        $concurrent = 0;
        $maxConcurrent = 0;
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', false, false, false, true);
        self::assertTrue($c->isConnected());
        $ch->consume(async(static function (Message $msg, Channel $ch, Client $c) use (&$concurrent, &$maxConcurrent, &$count, $deferred): void {
            $count++;
            $concurrent++;
            if ($concurrent > $maxConcurrent) {
                $maxConcurrent = $concurrent;
            }

            await(sleep(0.1));
            $concurrent--;

            if ($count === 1337) {
                $deferred->resolve($maxConcurrent);
            }
        }), concurrency: 13);
        self::assertTrue($c->isConnected());
        for ($i = 0; $i <= 1337; $i++) {
            $ch->publish('hi', [], '', 'test_queue');
        }

        self::assertSame(13, await($deferred->promise()));

        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testHeaders(): void
    {
        /**
         * @var Deferred<bool> $deferred
         */
        $deferred = new Deferred();
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->queueDeclare('test_queue', false, false, false, true);
        $ch->consume(static function (Message $msg, Channel $ch, Client $c) use ($deferred): void {
            self::assertTrue($msg->hasHeader('content-type'));
            self::assertEquals('text/html', $msg->getHeader('content-type'));
            self::assertEquals('<b>hi html</b>', $msg->content);
            $deferred->resolve(true);
        });
        $ch->publish('<b>hi html</b>', ['content-type' => 'text/html'], '', 'test_queue');
        self::assertTrue(await($deferred->promise()));

        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testBigMessage(): void
    {
        /**
         * @var Deferred<bool> $deferred
         */
        $deferred = new Deferred();
        $body = str_repeat('a', 10 << 20 /* 10 MiB */);

        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->queueDeclare('test_queue', false, false, false, true);
        $ch->consume(static function (Message $msg, Channel $ch, Client $c) use ($body, $deferred): void {
            self::assertEquals($body, $msg->content);
            $deferred->resolve(true);
        });
        $ch->publish($body, [], '', 'test_queue');
        self::assertTrue(await($deferred->promise()));

        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testChannelClosingMidMessageHandling(): void
    {
        /**
         * @var Deferred<string> $deferred
         */
        $deferred = new Deferred();
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', false, false, false, true);
        self::assertTrue($c->isConnected());
        $ch->consume(async(static function (Message $msg, Channel $ch, Client $c) use ($deferred): void {
            $c->disconnect();
            $deferred->resolve($msg->content);
        }), 'test_queue');
        self::assertTrue($c->isConnected());
        $ch->publish('hi', [], '', 'test_queue');
        self::assertEquals('hi', await($deferred->promise()));

        self::assertFalse($c->isConnected());
    }

    public function testAttemptingToOperateOnANoLongerConnectedConnectionThrows(): void
    {
        self::expectException(ChannelException::class);
        self::expectExceptionMessage('Channel is closed');

        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
        $ch->publish('hi', [], '', 'test_queue');
    }

    public function testEmitErrorInSteadOfSwallowingOnFrameReceived(): void
    {
        $c = $this->helper->createClient();

        $clientError = null;
        $c->on('error', static function (Throwable $err) use (&$clientError): void {
            $clientError = $err;
        });

        $ch = $c->connect()->channel();

        $channelError = null;
        $ch->on('error', static function (Throwable $err) use (&$channelError): void {
            $channelError = $err;
        });

        self::assertInstanceOf(Channel::class, $ch);
        $ch->onFrameReceived(new class (Constants::CLASS_CONNECTION) extends AbstractFrame {
        });

        self::assertNotNull($clientError);
        self::assertInstanceOf(ChannelException::class, $clientError);
        self::assertStringContainsString('Unhandled frame Bunny\Protocol\AbstractFrame@anonymous', $clientError->getMessage());

        self::assertNotNull($channelError);
        self::assertInstanceOf(ChannelException::class, $channelError);
        self::assertStringContainsString('Unhandled frame Bunny\Protocol\AbstractFrame@anonymous', $channelError->getMessage());

        self::assertSame(spl_object_id($clientError), spl_object_id($channelError));
    }
}
