<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bunny;

use Bunny\Async\Client;
use Bunny\Exception\ClientException;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Test\Exception\TimeoutException;
use Bunny\Test\Library\AsynchronousClientHelper;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory;
use React\EventLoop\Loop;
use React\Promise;

class AsyncClientTest extends TestCase
{
    /**
     * @var AsynchronousClientHelper
     */
    private $helper;

    public function setUp(): void
    {
        parent::setUp();

        $this->helper = new AsynchronousClientHelper();
    }

    public function testConnect()
    {
        Loop::addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = $this->helper->createClient();

        $this->assertFalse($client->isConnected());

        $client->connect()->then(function (Client $client) {
            $this->assertTrue($client->isConnected());

            return $client->disconnect();
        })->then(function (Client $client) {
            $this->assertFalse($client->isConnected());
            Loop::stop();
        })->done();

        Loop::run();

        $this->assertFalse($client->isConnected());
    }

    public function testConnectFailure()
    {
        $this->expectException(ClientException::class);

        Loop::addTimer(5, function () {
            throw new TimeoutException();
        });

        $options = $this->helper->getDefaultOptions();

        $options['vhost'] = 'bogus-vhost';

        $client = $this->helper->createClient($options);

        $client->connect()->then(function () {
            $this->fail("client should not connect");
            Loop::stop();
        })->done();

        Loop::run();
    }

    public function testOpenChannel()
    {
        Loop::addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = $this->helper->createClient();
        $client->connect()->then(function (Client $client) {
            return $client->channel();
        })->then(function (Channel $ch) {
            return $ch->getClient()->disconnect();
        })->then(function () {
            Loop::stop();
        })->done();

        Loop::run();

        $this->assertTrue(true);
    }

    public function testOpenMultipleChannel()
    {
        Loop::addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = $this->helper->createClient();
        $client->connect()->then(function (Client $client) {
            return Promise\all([
                $client->channel(),
                $client->channel(),
                $client->channel(),
            ]);
        })->then(function (array $chs) {
            /** @var Channel[] $chs */
            $this->assertCount(3, $chs);
            for ($i = 0, $l = count($chs); $i < $l; ++$i) {
                $this->assertInstanceOf(Channel::class, $chs[$i]);
                for ($j = 0; $j < $i; ++$j) {
                    $this->assertNotEquals($chs[$i]->getChannelId(), $chs[$j]->getChannelId());
                }
            }

            return $chs[0]->getClient()->disconnect();

        })->then(function () {
            Loop::stop();
        })->done();

        Loop::run();
    }

    public function testConflictingQueueDeclareRejects()
    {
        Loop::addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = $this->helper->createClient();
        $client->connect()->then(function (Client $client) {
            return $client->channel();
        })->then(function (Channel $ch) {
            return Promise\all([
                $ch->queueDeclare("conflict", false, false),
                $ch->queueDeclare("conflict", false, true),
            ]);
        })->then(function () {
            $this->fail("Promise should get rejected");
            Loop::stop();
        }, function (\Exception $e) {
            $this->assertInstanceOf(ClientException::class, $e);
            Loop::stop();
        })->done();

        Loop::run();
    }

    public function testDisconnectWithBufferedMessages()
    {
        Loop::addTimer(5, function () {
            throw new TimeoutException();
        });

        $processed = 0;

        $client = $this->helper->createClient();
        $client->connect()->then(function (Client $client) {
            return $client->channel();
        })->then(function (Channel $channel) use ($client, &$processed) {
            return Promise\all([
                $channel->qos(0, 1000),
                $channel->queueDeclare("disconnect_test"),
                $channel->consume(function (Message $message, Channel $channel) use ($client, &$processed) {
                    $channel->ack($message);

                    ++$processed;

                    $client->disconnect()->done(function () {
                        Loop::stop();
                    });

                }, "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
            ]);
        })->done();

        Loop::run();

        // all messages should be processed
        $this->assertEquals(1, $processed);

        // Clean-up Queue
        $client->connect()->then(function (Client $client) {
            return $client->channel();
        })->then(function (Channel $channel) use ($client, &$processed) {
            return Promise\all([
                $channel->queueDelete("disconnect_test"),
                $client->disconnect()->done(function () {
                    Loop::stop();
                })
            ]);
        })->done();

        Loop::run();
    }

    public function testGet()
    {
        Loop::addTimer(1, function () {
            throw new TimeoutException();
        });

        $client = $this->helper->createClient();
        /** @var Channel $channel */
        $channel = null;
        $client->connect()->then(function (Client $client) {
            return $client->channel();

        })->then(function (Channel $ch) use (&$channel) {
            $channel = $ch;

            return Promise\all([
                $channel->queueDeclare("get_test"),
                $channel->publish(".", [], "", "get_test"),
            ]);

        })->then(function () use (&$channel) {
            return $channel->get("get_test", true);

        })->then(function (Message $message1 = null) use (&$channel) {
            $this->assertNotNull($message1);
            $this->assertInstanceOf(Message::class, $message1);
            $this->assertEquals($message1->exchange, "");
            $this->assertEquals($message1->content, ".");

            return $channel->get("get_test", true);

        })->then(function (Message $message2 = null) use (&$channel) {
            $this->assertNull($message2);

            return $channel->publish("..", [], "", "get_test");

        })->then(function () use (&$channel) {
            return $channel->get("get_test");

        })->then(function (Message $message3 = null) use (&$channel) {
            $this->assertNotNull($message3);
            $this->assertInstanceOf(Message::class, $message3);
            $this->assertEquals($message3->exchange, "");
            $this->assertEquals($message3->content, "..");

            $channel->ack($message3);

            return $channel->getClient()->disconnect();

        })->then(function () {
            Loop::stop();
        })->done();

        Loop::run();
    }

    public function testReturn()
    {
        Loop::addTimer(1, function () {
            throw new TimeoutException();
        });

        $client = $this->helper->createClient();

        /** @var Channel $channel */
        $channel = null;
        /** @var Message $returnedMessage */
        $returnedMessage = null;
        /** @var MethodBasicReturnFrame $returnedFrame */
        $returnedFrame = null;

        $client->connect()->then(function (Client $client) {
            return $client->channel();

        })->then(function (Channel $ch) use (&$channel, &$returnedMessage, &$returnedFrame) {
            $channel = $ch;

            $channel->addReturnListener(function (Message $message, MethodBasicReturnFrame $frame) use (&$returnedMessage, &$returnedFrame) {
                $returnedMessage = $message;
                $returnedFrame = $frame;
                Loop::stop();
            });

            return $channel->publish("xxx", [], "", "404", true);
        })->done();

        Loop::run();

        $this->assertNotNull($returnedMessage);
        $this->assertInstanceOf(Message::class, $returnedMessage);
        $this->assertEquals("xxx", $returnedMessage->content);
        $this->assertEquals("", $returnedMessage->exchange);
        $this->assertEquals("404", $returnedMessage->routingKey);
    }

    public function testHeartBeatCallback()
    {
        Loop::addTimer(3, function () {
            throw new TimeoutException();
        });

        $called = 0;

        $defaultOptions = $this->helper->getDefaultOptions();

        $client = $this->helper->createClient(array_merge($defaultOptions, [
            'heartbeat' => 1.0,
            'heartbeat_callback' => function () use (&$called) {
                $called += 1;
            }
        ]));

        $client->connect()->then(function (Client $client) {
            sleep(1);
            return $client->channel();
        })->then(function (Channel $ch) {
            sleep(1);
            return $ch->queueDeclare('hello', false, false, false, false)->then(function () use ($ch) {
                return $ch;
            });
        })->then(function (Channel $ch) {
            return $ch->getClient()->disconnect();
        })->then(function () {
            Loop::stop();
        })->done();

        Loop::run();

        $this->assertEquals(2, $called);
    }

}
