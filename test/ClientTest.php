<?php

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Channel;
use Bunny\Exception\ChannelException;
use Bunny\Exception\ClientException;
use Bunny\Message;
use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Test\Library\ClientHelper;
use Bunny\Test\Library\Environment;
use Bunny\Test\Library\Paths;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Promise;
use WyriHaximus\React\PHPUnit\RunTestsInFibersTrait;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Stream\buffer;
use function React\Promise\Timer\sleep;
use function React\Promise\all;
use function array_unique;
use function count;
use function implode;
use const SIGINT;

class ClientTest extends TestCase
{
    use RunTestsInFibersTrait;

    private ClientHelper $helper;

    public function setUp(): void
    {
        parent::setUp();

        $this->helper = new ClientHelper();
    }

    public function testConnect(): void
    {
        $closeEmitted = null;
        $client = $this->helper->createClient();
        $client->on('close', static function () use (&$closeEmitted): void {
            $closeEmitted = true;
        });

        self::assertFalse($client->isConnected());

        $client->connect();

        self::assertTrue($client->isConnected());
        self::assertNull($closeEmitted);
        $client->disconnect();
        self::assertFalse($client->isConnected());
        self::assertTrue($closeEmitted);
    }

    public function testConnectWithInvalidClientProperties(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->helper->createClient(['client_properties' => 'not an array']);
    }

    public function testConnectFailure(): void
    {
        $this->expectException(ClientException::class);

        $options = $this->helper->getDefaultOptions();

        $options['vhost'] = 'bogus-vhost';

        $client = $this->helper->createClient($options);

        $client->connect();
    }

    public function testOpenChannel(): void
    {
        $client = $this->helper->createClient();

        $client->connect();

        $channel = $client->channel();

        self::assertInstanceOf(Channel::class, $channel);

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testOpenMultipleChannel(): void
    {
        $client = $this->helper->createClient();
        $client->connect();
        self::assertInstanceOf(Channel::class, $ch1 = $client->channel());
        self::assertInstanceOf(Channel::class, $ch2 = $client->channel());
        self::assertNotEquals($ch1->getChannelId(), $ch2->getChannelId());
        self::assertInstanceOf(Channel::class, $ch3 = $client->channel());
        self::assertNotEquals($ch1->getChannelId(), $ch3->getChannelId());
        self::assertNotEquals($ch2->getChannelId(), $ch3->getChannelId());

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testOpenMultipleChannelAsync(): void
    {
        $client = $this->helper->createClient();

        self::assertFalse($client->isConnected());

        $tasks = [];
        for ($i = 0; $i < 5; $i++) {
            $tasks[] = async(static fn () => $client->channel())();
        }

        $channels = await(all($tasks));

        self::assertCount(count($tasks), $channels);

        $channelIds = [];
        foreach ($channels as $ch) {
            self::assertInstanceOf(Channel::class, $ch);
            $channelIds[] = $ch->getChannelId();
        }

        self::assertSame($channelIds, array_unique($channelIds));

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testDisconnectWithBufferedMessages(): void
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $processed = 0;

        $channel->qos(0, 1000);
        $channel->queueDeclare('disconnect_test');
        $channel->consume(async(static function (Message $message, Channel $channel) use ($client, &$processed): void {
            $channel->ack($message);
            ++$processed;
            $client->disconnect();
        }));
        $channel->publish('.', [], '', 'disconnect_test');
        $channel->publish('.', [], '', 'disconnect_test');
        $channel->publish('.', [], '', 'disconnect_test');

        await(sleep(5));

        self::assertEquals(1, $processed);
        self::assertFalse($client->isConnected());

        // Clean-up Queue
        $client = $this->helper->createClient();
        $channel = $client->channel();
        $channel->queueDelete('disconnect_test');
        $client->disconnect();
    }

    /**
     * Spawns an external consumer process, and tries to stop it with SIGINT.
     */
    public function testStopConsumerWithSigInt(): void
    {
        $queueName = 'stop-consumer-with-sigint';

        $path = Paths::getTestsRootPath() . '/scripts/bunny-consumer.php';

        $process = new Process(implode(' ', [$path, Environment::getTestRabbitMqConnectionUri(), $queueName, '0']));

        Loop::futureTick(static function () use ($process): void {
            $process->start();
        });

        // Send SIGINT after 1.0 seconds
        Loop::addTimer(1, static function () use ($process): void {
            $process->terminate(SIGINT);
        });

        $termination = new Promise(static function (callable $resolve) use ($process): void {
            $process->on('exit', static function ($code) use ($resolve): void {
                $resolve($code === 0);
            });
        });

        self::assertTrue(await($termination), await(buffer($process->stdout)) . "\n" . await(buffer($process->stderr)));
    }

    public function testGet(): void
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare('get_test');
        $channel->publish('.', [], '', 'get_test');

        $message1 = $channel->get('get_test', true);
        self::assertNotNull($message1);
        self::assertInstanceOf(Message::class, $message1);
        self::assertEquals($message1->exchange, '');
        self::assertEquals($message1->content, '.');

        $message2 = $channel->get('get_test', true);
        self::assertNull($message2);

        $channel->publish('..', [], '', 'get_test');

        $channel->get('get_test');
        $client->disconnect();

        await(sleep(5));

        $client->connect();

        $channel  = $client->channel();
        $message3 = $channel->get('get_test');
        self::assertNotNull($message3);
        self::assertInstanceOf(Message::class, $message3);
        self::assertEquals($message3->exchange, '');
        self::assertEquals($message3->content, '..');

        $channel->ack($message3);

        $client->disconnect();

        await(sleep(5));

        self::assertFalse($client->isConnected());
    }

    public function testReturn(): void
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $returnedMessage = null;
        $channel->addReturnListener(static function (
            Message $message,
            MethodBasicReturnFrame $frame,
        ) use (
            &$returnedMessage,
        ): void {
            $returnedMessage = $message;
        });

        $channel->publish('xxx', [], '', '404', true);

        await(sleep(1));

        self::assertNotNull($returnedMessage);
        self::assertInstanceOf(Message::class, $returnedMessage);
        self::assertEquals('xxx', $returnedMessage->content);
        self::assertEquals('', $returnedMessage->exchange);
        self::assertEquals('404', $returnedMessage->routingKey);

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testTxs(): void
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare('tx_test');

        $channel->txSelect();
        $channel->publish('.', [], '', 'tx_test');
        $channel->txCommit();

        $message = $channel->get('tx_test', true);
        self::assertNotNull($message);
        self::assertEquals('.', $message->content);

        $channel->publish('..', [], '', 'tx_test');
        $channel->txRollback();

        $nothing = $channel->get('tx_test', true);
        self::assertNull($nothing);

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testTxSelectCannotBeCalledMultipleTimes(): void
    {
        $this->expectException(ChannelException::class);

        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->txSelect();
        $channel->txSelect();

        self::assertTrue($client->isConnected());
        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testConfirmMode(): void
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $deliveryTag = null;
        $channel->confirmSelect(async(static function (MethodBasicAckFrame $frame) use (&$deliveryTag, $client): void {
            if ($frame->deliveryTag === $deliveryTag) {
                $deliveryTag = null;
                $client->disconnect();
            }
        }));

        $deliveryTag = $channel->publish('tst_cfm_m');

        await(sleep(1));

        self::assertNull($deliveryTag);

        self::assertFalse($client->isConnected());
    }

    public function testEmptyMessage(): void
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare('empty_body_message_test');

        $channel->publish('', [], '', 'empty_body_message_test');
        $message = $channel->get('empty_body_message_test', true);
        self::assertNotNull($message);
        self::assertEquals('', $message->content);

        $processed = 0;
        $channel->consume(
            async(static function (Message $message, Channel $channel) use ($client, &$processed): void {
                self::assertEmpty($message->content);
                $channel->ack($message);
                if (++$processed === 2) {
                    $client->disconnect();
                }
            }),
            'empty_body_message_test',
        );

        $channel->publish('', [], '', 'empty_body_message_test');
        $channel->publish('', [], '', 'empty_body_message_test');

        await(sleep(0.01));

        self::assertFalse($client->isConnected());
    }

    public function testHeartBeatCallback(): void
    {
        $called = 0;

        $options = $this->helper->getDefaultOptions();

        $options['heartbeat']          = 0.1;
        $options['heartbeat_callback'] = static function () use (&$called): void {
            $called += 1;
        };

        $client = $this->helper->createClient($options);

        $client->connect();

        await(sleep(0.2));

        $client->disconnect();

        self::assertGreaterThan(0, $called);

        self::assertFalse($client->isConnected());
    }
}
