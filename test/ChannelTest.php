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
use Bunny\Test\Library\ClientFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use Throwable;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;
use function spl_object_id;
use function str_repeat;

final class ChannelTest extends TestCase
{
    public function testClose(): void
    {
        $client = ClientFactory::createClient();

        $client->connect();
        $client->channel()->close();

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testExchangeDeclare(): void
    {
        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->exchangeDeclare('test_exchange', autoDelete: true);

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testQueueDeclare(): void
    {
        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->queueDeclare('test_queue', durable: true, autoDelete: true);

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testQueueBind(): void
    {
        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->exchangeDeclare('test_exchange', autoDelete: true);
        $channel->queueDeclare('test_queue', durable: true, autoDelete: true);
        $channel->queueBind('test_exchange', 'test_queue');

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testPublish(): void
    {
        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->publish('test publish');

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testConsume(): void
    {
        /** @var Deferred<string> $deferred */
        $deferred = new Deferred();

        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->queueDeclare('test_queue', durable: true, autoDelete: true);
        $channel->consume(static function (Message $message, Channel $channel, Client $client) use (&$deferred): void {
            $deferred->resolve($message->content);
        }, 'test_queue');

        $channel->publish('hi', routingKey: 'test_queue');

        self::assertEquals('hi', await($deferred->promise()));

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
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
     * @param positive-int $concurrency This is a lie to stop PHPStan from complaining this is an int
     */
    #[DataProvider('invalidConsumeConcurrencyProvider')]
    public function testConsumeWithInvalidConcurrency(int $concurrency): void
    {
        self::expectException(ChannelException::class);
        self::expectExceptionMessage('basic.consume concurrency must be 1 or higher');

        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->queueDeclare('test_queue', durable: true, autoDelete: true);

        try {
            $channel->consume(static function (Message $message, Channel $channel, Client $client): void {
            }, concurrency: $concurrency);
            $channel->publish('hi', routingKey:  'test_queue');
        } finally {
            self::assertTrue($client->isConnected());
            $client->disconnect();
            self::assertFalse($client->isConnected());
        }
    }

    public function testConsumeWithConcurrencyDoesntConsumeMoreMessagesConcurrentlyThanConfigured(): void
    {
        /** @var Deferred<int> $deferred */
        $deferred = new Deferred();
        $count = 0;
        $concurrent = 0;
        $maxConcurrent = 0;

        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->queueDeclare('test_queue', durable: true, autoDelete: true);
        $channel->consume(async(static function (Message $message, Channel $channel, Client $client) use (&$concurrent, &$maxConcurrent, &$count, &$deferred): void {
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

        for ($i = 0; $i <= 1337; $i++) {
            $channel->publish('hi', routingKey: 'test_queue');
        }

        self::assertSame(13, await($deferred->promise()));

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testHeaders(): void
    {
        /** @var Deferred<bool> $deferred */
        $deferred = new Deferred();

        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->queueDeclare('test_queue', durable: true, autoDelete: true);
        $channel->consume(static function (Message $message, Channel $channel, Client $client) use (&$deferred): void {
            self::assertTrue($message->hasHeader('content-type'));
            self::assertEquals('text/html', $message->getHeader('content-type'));
            self::assertEquals('<b>hi html</b>', $message->content);
            $deferred->resolve(true);
        });

        $channel->publish('<b>hi html</b>', headers: ['content-type' => 'text/html'], routingKey: 'test_queue');

        self::assertTrue(await($deferred->promise()));

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testBigMessage(): void
    {
        /** @var Deferred<bool> $deferred */
        $deferred = new Deferred();
        $body = str_repeat('a', 10 << 20 /* 10 MiB */);

        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->queueDeclare('test_queue', durable: true, autoDelete: true);
        $channel->consume(static function (Message $message, Channel $channel, Client $client) use ($body, &$deferred): void {
            self::assertEquals($body, $message->content);
            $deferred->resolve(true);
        });

        $channel->publish($body, routingKey: 'test_queue');

        self::assertTrue(await($deferred->promise()));

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testChannelClosingMidMessageHandling(): void
    {
        /** @var Deferred<string> $deferred */
        $deferred = new Deferred();

        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $channel->queueDeclare('test_queue', durable: true, autoDelete: true);
        $channel->consume(async(static function (Message $message, Channel $channel, Client $client) use (&$deferred): void {
            $client->disconnect();
            $deferred->resolve($message->content);
        }), 'test_queue');

        $channel->publish('hi', routingKey:  'test_queue');

        self::assertEquals('hi', await($deferred->promise()));

        self::assertFalse($client->isConnected());
    }

    public function testAttemptingToOperateOnANoLongerConnectedConnectionThrows(): void
    {
        self::expectException(ChannelException::class);
        self::expectExceptionMessage('Channel is closed');

        $client = ClientFactory::createClient();

        $channel = $client->channel();

        $client->disconnect();

        self::assertFalse($client->isConnected());

        $channel->publish('hi', routingKey: 'test_queue');
    }

    public function testEmitErrorInSteadOfSwallowingOnFrameReceived(): void
    {
        $client = ClientFactory::createClient();

        $clientError = null;
        $client->on('error', static function (Throwable $err) use (&$clientError): void {
            $clientError = $err;
        });

        $channel = $client->channel();

        $channelError = null;
        $channel->on('error', static function (Throwable $err) use (&$channelError): void {
            $channelError = $err;
        });

        self::assertInstanceOf(Channel::class, $channel);
        $channel->onFrameReceived(new class (Constants::CLASS_CONNECTION) extends AbstractFrame {
        });

        self::assertInstanceOf(ChannelException::class, $clientError);
        self::assertStringContainsString('Unhandled frame Bunny\Protocol\AbstractFrame@anonymous', $clientError->getMessage());

        self::assertInstanceOf(ChannelException::class, $channelError);
        self::assertStringContainsString('Unhandled frame Bunny\Protocol\AbstractFrame@anonymous', $channelError->getMessage());

        self::assertSame(spl_object_id($clientError), spl_object_id($channelError));

        $client->disconnect();
    }
}
