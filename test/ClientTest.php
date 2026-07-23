<?php

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Configuration;
use Bunny\Constants;
use Bunny\Exception\ChannelException;
use Bunny\Exception\ClientException;
use Bunny\Message;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Protocol\MethodChannelOpenOkFrame;
use Bunny\Protocol\MethodConnectionOpenOkFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use Bunny\Protocol\MethodConnectionTuneFrame;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;
use Bunny\Test\Library\ClientFactory;
use Bunny\Test\Library\Environment;
use Bunny\Test\Library\Paths;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use React\Promise\Promise;
use React\Socket\ConnectorInterface;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Stream\buffer;
use function React\Promise\Timer\sleep;
use function React\Promise\all;
use function React\Promise\resolve;
use function array_unique;
use function count;
use function implode;
use function str_repeat;
use const SIGINT;

final class ClientTest extends TestCase
{
    public function testPublishFragmentsBodyUsingNegotiatedFrameMax(): void
    {
        $previousLoop = Loop::get();
        Loop::set(new StreamSelectLoop());

        try {
            $socketConnection = new MockConnectionInterface();
            $connector = $this->createMock(ConnectorInterface::class);
            $connector
                ->method('connect')
                ->willReturn(resolve($socketConnection));

            $start = new MethodConnectionStartFrame();
            $start->mechanisms = 'AMQPLAIN';

            $tune = new MethodConnectionTuneFrame();
            $tune->frameMax = Constants::FRAME_MIN_SIZE;
            $tune->channelMax = 2047;

            $channelOpenOk = new MethodChannelOpenOkFrame();
            $channelOpenOk->channel = 1;

            $serverFrames = [
                $start,
                $tune,
                new MethodConnectionOpenOkFrame(),
                $channelOpenOk,
            ];
            $protocolWriter = new ProtocolWriter();
            foreach ($serverFrames as $serverFrame) {
                Loop::futureTick(static function () use ($protocolWriter, $serverFrame, $socketConnection): void {
                    $buffer = new Buffer();
                    $protocolWriter->appendFrame($serverFrame, $buffer);
                    $socketConnection->emit('data', [$buffer->consume($buffer->getLength())]);
                });
            }

            $client = new Client(new Configuration(heartbeat: 0, connector: $connector));
            $channel = $client->channel();
            $socketConnection->clearWrittenData();

            $channel->publish(str_repeat('x', $tune->frameMax + 4));

            $buffer = new Buffer($socketConnection->getWrittenData());
            $protocolReader = new ProtocolReader();
            $bodyPayloadSizes = [];
            while (($frame = $protocolReader->consumeFrame($buffer)) !== null) {
                if ($frame instanceof ContentBodyFrame) {
                    $bodyPayloadSizes[] = $frame->payloadSize;
                }
            }

            self::assertSame([$tune->frameMax - 8, 12], $bodyPayloadSizes);
        } finally {
            Loop::set($previousLoop);
        }
    }

    public function testConnect(): void
    {
        $client = ClientFactory::createClient();

        $closeEmitted = null;
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

        ClientFactory::createClient(['client_properties' => 'not an array']);
    }

    public function testConnectFailure(): void
    {
        $this->expectException(ClientException::class);

        $options = ClientFactory::getDefaultOptions();
        $options['vhost'] = 'bogus-vhost';

        $client = ClientFactory::createClient($options);

        $client->connect();
    }

    public function testOpenChannel(): void
    {
        $client = ClientFactory::createClient();

        $channel = $client->channel();
        self::assertInstanceOf(Channel::class, $channel);

        $client->disconnect();
    }

    public function testOpenMultipleChannel(): void
    {
        $client = ClientFactory::createClient();

        self::assertInstanceOf(Channel::class, $ch1 = $client->channel());
        self::assertInstanceOf(Channel::class, $ch2 = $client->channel());
        self::assertNotEquals($ch1->getChannelId(), $ch2->getChannelId());
        self::assertInstanceOf(Channel::class, $ch3 = $client->channel());
        self::assertNotEquals($ch1->getChannelId(), $ch3->getChannelId());
        self::assertNotEquals($ch2->getChannelId(), $ch3->getChannelId());

        $client->disconnect();
    }

    public function testOpenMultipleChannelAsync(): void
    {
        $client = ClientFactory::createClient();

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

        $client->disconnect();
    }

    public function testDisconnectWithBufferedMessages(): void
    {
        $client = ClientFactory::createClient();
        $channel = $client->channel();

        $processed = 0;

        $channel->qos(0, 1000);
        $channel->queueDeclare('disconnect_test', durable: true);
        $channel->consume(async(static function (Message $message, Channel $channel) use ($client, &$processed): void {
            $channel->ack($message);
            ++$processed;
            $client->disconnect();
        }));
        $channel->publish('.', [], '', 'disconnect_test');
        $channel->publish('.', [], '', 'disconnect_test');
        $channel->publish('.', [], '', 'disconnect_test');

        await(sleep(2));

        self::assertEquals(1, $processed);
        self::assertFalse($client->isConnected());

        // Clean-up Queue
        $client = ClientFactory::createClient();
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
        $client = ClientFactory::createClient();
        $channel = $client->channel();

        $channel->queueDeclare('get_test', durable: true);
        $channel->publish('.', [], '', 'get_test');

        $message1 = $channel->get('get_test', noAck: true);
        self::assertNotNull($message1);
        self::assertEquals($message1->exchange, '');
        self::assertEquals($message1->content, '.');

        $message2 = $channel->get('get_test', noAck: true);
        self::assertNull($message2);

        $channel->publish('..', [], '', 'get_test');

        $channel->get('get_test');
        $client->disconnect();

        await(sleep(2));

        $client->connect();
        $channel = $client->channel();

        $message3 = $channel->get('get_test');
        self::assertNotNull($message3);
        self::assertEquals($message3->exchange, '');
        self::assertEquals($message3->content, '..');

        $channel->ack($message3);

        $client->disconnect();

        await(sleep(2));

        self::assertFalse($client->isConnected());
    }

    public function testReturn(): void
    {
        $client = ClientFactory::createClient();
        $channel = $client->channel();

        $returnedMessage = null;
        $channel->addReturnListener(static function (Message $message, MethodBasicReturnFrame $frame) use (&$returnedMessage): void {
            $returnedMessage = $message;
        });

        $channel->publish('xxx', [], '', '404', true);

        await(sleep(1));

        self::assertNotNull($returnedMessage);
        self::assertEquals('xxx', $returnedMessage->content);
        self::assertEquals('', $returnedMessage->exchange);
        self::assertEquals('404', $returnedMessage->routingKey);

        $client->disconnect();
    }

    public function testTxs(): void
    {
        $client = ClientFactory::createClient();
        $channel = $client->channel();

        $channel->queueDeclare('tx_test', durable: true);

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

        $client->disconnect();
    }

    public function testTxSelectCannotBeCalledMultipleTimes(): void
    {
        $this->expectException(ChannelException::class);

        $client = ClientFactory::createClient();
        $channel = $client->channel();

        try {
            $channel->txSelect();
            $channel->txSelect();
        } finally {
            $client->disconnect();
        }
    }

    public function testConfirmMode(): void
    {
        $client = ClientFactory::createClient();
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
        $client = ClientFactory::createClient();
        $channel = $client->channel();

        $channel->queueDeclare('empty_body_message_test', durable: true);

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
        $options = ClientFactory::getDefaultOptions();

        $called = 0;
        $options['heartbeat']          = 0.1;
        $options['heartbeat_callback'] = static function () use (&$called): void {
            $called += 1;
        };

        $client = ClientFactory::createClient($options);
        $client->connect();

        await(sleep(0.2));

        $client->disconnect();

        self::assertGreaterThan(0, $called);

        self::assertFalse($client->isConnected());
    }
}
