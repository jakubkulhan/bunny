<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Channel;
use Bunny\Exception\ChannelException;
use Bunny\Exception\ClientException;
use Bunny\Message;
use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Test\Library\Environment;
use Bunny\Test\Library\Paths;
use Bunny\Test\Library\SynchronousClientHelper;
use PHPUnit\Framework\TestCase;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Promise;
use WyriHaximus\React\PHPUnit\RunTestsInFibersTrait;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Stream\buffer;
use const SIGINT;

class ClientTest extends TestCase
{
    use RunTestsInFibersTrait;

    /**
     * @var SynchronousClientHelper
     */
    private $helper;

    public function setUp(): void
    {
        parent::setUp();

        $this->helper = new SynchronousClientHelper();
    }

    public function testConnect()
    {
        $client = $this->helper->createClient();

        $this->assertFalse($client->isConnected());

        $client->connect();

        $this->assertTrue($client->isConnected());
        $client->disconnect();
        $this->assertFalse($client->isConnected());
    }

    public function testConnectWithInvalidClientProperties()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->helper->createClient([
            'client_properties' => 'not an array'
        ]);
    }

    public function testConnectFailure()
    {
        $this->expectException(ClientException::class);

        $options = $this->helper->getDefaultOptions();

        $options['vhost'] = 'bogus-vhost';

        $client = $this->helper->createClient($options);

        $client->connect();
    }

    public function testOpenChannel()
    {
        $client = $this->helper->createClient();

        $client->connect();

        $channel = $client->channel();

        $this->assertInstanceOf(Channel::class, $channel);

        $this->assertTrue($client->isConnected());
        $client->disconnect();
        $this->assertFalse($client->isConnected());
    }

    public function testOpenMultipleChannel()
    {
        $client = $this->helper->createClient();
        $client->connect();
        $this->assertInstanceOf(Channel::class, $ch1 = $client->channel());
        $this->assertInstanceOf(Channel::class, $ch2 = $client->channel());
        $this->assertNotEquals($ch1->getChannelId(), $ch2->getChannelId());
        $this->assertInstanceOf(Channel::class, $ch3 = $client->channel());
        $this->assertNotEquals($ch1->getChannelId(), $ch3->getChannelId());
        $this->assertNotEquals($ch2->getChannelId(), $ch3->getChannelId());

        $this->assertTrue($client->isConnected());
        $client->disconnect();
        $this->assertFalse($client->isConnected());
    }

    public function testDisconnectWithBufferedMessages()
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $processed = 0;

        $channel->qos(0, 1000);
        $channel->queueDeclare("disconnect_test");
        $channel->consume(async(function (Message $message, Channel $channel) use ($client, &$processed) {
            $channel->ack($message);
            ++$processed;
            $client->disconnect();
        }));
        $channel->publish(".", [], "", "disconnect_test");
        $channel->publish(".", [], "", "disconnect_test");
        $channel->publish(".", [], "", "disconnect_test");

        await(\React\Promise\Timer\sleep(5));

        $this->assertEquals(1, $processed);
        $this->assertFalse($client->isConnected());

        // Clean-up Queue
        $client = $this->helper->createClient();
        $channel = $client->channel();
        $channel->queueDelete("disconnect_test");
        $client->disconnect();
    }

    /**
     * Spawns an external consumer process, and tries to stop it with SIGINT.
     */
    public function testStopConsumerWithSigInt()
    {
        $queueName = 'stop-consumer-with-sigint';

        $path = Paths::getTestsRootPath() . '/scripts/bunny-consumer.php';

        $process = new Process($path . ' ' . Environment::getTestRabbitMqConnectionUri() . ' ' .$queueName . ' ' . '0');

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

    public function testGet()
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare("get_test");
        $channel->publish(".", [], "", "get_test");

        $message1 = $channel->get("get_test", true);
        $this->assertNotNull($message1);
        $this->assertInstanceOf(Message::class, $message1);
        $this->assertEquals($message1->exchange, "");
        $this->assertEquals($message1->content, ".");

        $message2 = $channel->get("get_test", true);
        $this->assertNull($message2);

        $channel->publish("..", [], "", "get_test");

        $channel->get("get_test");
        $client->disconnect();

        await(\React\Promise\Timer\sleep(5));

        $client->connect();

        $channel  = $client->channel();
        $message3 = $channel->get("get_test");
        $this->assertNotNull($message3);
        $this->assertInstanceOf(Message::class, $message3);
        $this->assertEquals($message3->exchange, "");
        $this->assertEquals($message3->content, "..");

        $channel->ack($message3);

        $client->disconnect();

        await(\React\Promise\Timer\sleep(5));

        $this->assertFalse($client->isConnected());
    }

    public function testReturn()
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        /** @var Message $returnedMessage */
        $returnedMessage = null;
        $channel->addReturnListener(function (
            Message $message,
            MethodBasicReturnFrame $frame
        ) use (
            $client,
            &$returnedMessage
        ) {
            $returnedMessage = $message;
        });

        $channel->publish("xxx", [], "", "404", true);

        await(\React\Promise\Timer\sleep(1));

        $this->assertNotNull($returnedMessage);
        $this->assertInstanceOf(Message::class, $returnedMessage);
        $this->assertEquals("xxx", $returnedMessage->content);
        $this->assertEquals("", $returnedMessage->exchange);
        $this->assertEquals("404", $returnedMessage->routingKey);

        $this->assertTrue($client->isConnected());
        $client->disconnect();
        $this->assertFalse($client->isConnected());
    }

    public function testTxs()
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare("tx_test");

        $channel->txSelect();
        $channel->publish(".", [], "", "tx_test");
        $channel->txCommit();

        $message = $channel->get("tx_test", true);
        $this->assertNotNull($message);
        $this->assertEquals(".", $message->content);

        $channel->publish("..", [], "", "tx_test");
        $channel->txRollback();

        $nothing = $channel->get("tx_test", true);
        $this->assertNull($nothing);

        $this->assertTrue($client->isConnected());
        $client->disconnect();
        $this->assertFalse($client->isConnected());
    }

    public function testTxSelectCannotBeCalledMultipleTimes()
    {
        $this->expectException(ChannelException::class);

        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->txSelect();
        $channel->txSelect();

        $this->assertTrue($client->isConnected());
        $client->disconnect();
        $this->assertFalse($client->isConnected());
    }

    public function testConfirmMode()
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $deliveryTag = null;
        $channel->confirmSelect(async(function (MethodBasicAckFrame $frame) use (&$deliveryTag, $client) {
            if ($frame->deliveryTag === $deliveryTag) {
                $deliveryTag = null;
                $client->disconnect();
            }
        }));

        $deliveryTag = $channel->publish("tst_cfm_m");

        await(\React\Promise\Timer\sleep(1));

        $this->assertNull($deliveryTag);

        $this->assertFalse($client->isConnected());
    }

    public function testEmptyMessage()
    {
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare("empty_body_message_test");

        $channel->publish("", [], "", "empty_body_message_test");
        $message = $channel->get("empty_body_message_test", true);
        $this->assertNotNull($message);
        $this->assertEquals("", $message->content);

        $processed = 0;
        $channel->consume(
            async(function (Message $message, Channel $channel) use ($client, &$processed) {
                $this->assertEmpty($message->content);
                $channel->ack($message);
                if (++$processed === 2) {
                    $client->disconnect();
                }
            }),
            "empty_body_message_test"
        );

        $channel->publish("", [], "", "empty_body_message_test");
        $channel->publish("", [], "", "empty_body_message_test");

        await(\React\Promise\Timer\sleep(0.01));

        $this->assertFalse($client->isConnected());
    }

    public function testHeartBeatCallback()
    {
        $called = 0;

        $options = $this->helper->getDefaultOptions();

        $options['heartbeat']          = 0.1;
        $options['heartbeat_callback'] = function () use (&$called) {
            $called += 1;
        };

        $client = $this->helper->createClient($options);

        $client->connect();

        await(\React\Promise\Timer\sleep(0.2));

        $client->disconnect();

        $this->assertGreaterThan(0, $called);

        $this->assertFalse($client->isConnected());
    }
}
