<?php
namespace Bunny;

use Bunny\Async\Client;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Test\Exception\TimeoutException;
use React\EventLoop\Factory;
use React\Promise;

class AsyncClientTest extends \PHPUnit_Framework_TestCase
{

    public function testConnectAsGuest()
    {
        $loop = Factory::create();

        $loop->addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = new Client($loop);
        $client->connect()->then(function (Client $client) {
            return $client->disconnect();
        })->then(function () use ($loop) {
            $loop->stop();
        })->done();

        $loop->run();
    }

    public function testConnectAuth()
    {
        $loop = Factory::create();

        $loop->addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = new Client($loop, [
            "user" => "testuser",
            "password" => "testpassword",
            "vhost" => "testvhost",
        ]);
        $client->connect()->then(function (Client $client) {
            return $client->disconnect();
        })->then(function () use ($loop) {
            $loop->stop();
        })->done();

        $loop->run();
    }

    public function testConnectFailure()
    {
        $this->setExpectedException("Bunny\\Exception\\ClientException");

        $loop = Factory::create();

        $loop->addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = new Client($loop, [
            "user" => "testuser",
            "password" => "testpassword",
            "vhost" => "/",
        ]);
        $client->connect()->then(function () use ($loop) {
            $this->fail("client should not connect");
            $loop->stop();
        })->done();

        $loop->run();
    }

    public function testOpenChannel()
    {
        $loop = Factory::create();

        $loop->addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = new Client($loop);
        $client->connect()->then(function (Client $client) {
            return $client->channel();
        })->then(function (Channel $ch) {
            return $ch->getClient()->disconnect();
        })->then(function () use ($loop) {
            $loop->stop();
        })->done();

        $loop->run();
    }

    public function testOpenMultipleChannel()
    {
        $loop = Factory::create();

        $loop->addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = new Client($loop);
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
                $this->assertInstanceOf("Bunny\\Channel", $chs[$i]);
                for ($j = 0; $j < $i; ++$j) {
                    $this->assertNotEquals($chs[$i]->getChannelId(), $chs[$j]->getChannelId());
                }
            }

            return $chs[0]->getClient()->disconnect();

        })->then(function () use ($loop) {
            $loop->stop();
        })->done();

        $loop->run();
    }

    public function testConflictingQueueDeclareRejects()
    {
        $loop = Factory::create();

        $loop->addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = new Client($loop);
        $client->connect()->then(function (Client $client) {
            return $client->channel();
        })->then(function (Channel $ch) {
            return Promise\all([
                $ch->queueDeclare("conflict", false, false),
                $ch->queueDeclare("conflict", false, true),
            ]);
        })->then(function () use ($loop) {
            $this->fail("Promise should get rejected");
            $loop->stop();
        }, function (\Exception $e) use ($loop) {
            $this->assertInstanceOf("Bunny\\Exception\\ClientException", $e);
            $loop->stop();
        })->done();

        $loop->run();
    }

    public function testDisconnectWithBufferedMessages()
    {
        $loop = Factory::create();

        $loop->addTimer(5, function () {
            throw new TimeoutException();
        });

        $processed = 0;

        $client = new Client($loop);
        $client->connect()->then(function (Client $client) {
            return $client->channel();
        })->then(function (Channel $channel) use ($client, $loop, &$processed) {
            return Promise\all([
                $channel->qos(0, 1000),
                $channel->queueDeclare("disconnect_test"),
                $channel->consume(function (Message $message, Channel $channel) use ($client, $loop, &$processed) {
                    $channel->ack($message);

                    ++$processed;

                    $client->disconnect()->done(function () use ($loop) {
                        $loop->stop();
                    });

                }, "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
            ]);
        })->done();

        $loop->run();

        // all messages should be processed
        $this->assertEquals(1, $processed);
    }

    public function testGet()
    {
        $loop = Factory::create();

        $loop->addTimer(1, function () {
            throw new TimeoutException();
        });

        $client = new Client($loop);
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
            $this->assertInstanceOf("Bunny\\Message", $message1);
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
            $this->assertInstanceOf("Bunny\\Message", $message3);
            $this->assertEquals($message3->exchange, "");
            $this->assertEquals($message3->content, "..");

            $channel->ack($message3);

            return $channel->getClient()->disconnect();

        })->then(function () use ($loop) {
            $loop->stop();
        })->done();

        $loop->run();
    }

    public function testReturn()
    {
        $loop = Factory::create();

        $loop->addTimer(1, function () {
            throw new TimeoutException();
        });

        $client = new Client($loop);

        /** @var Channel $channel */
        $channel = null;
        /** @var Message $returnedMessage */
        $returnedMessage = null;
        /** @var MethodBasicReturnFrame $returnedFrame */
        $returnedFrame = null;

        $client->connect()->then(function (Client $client) {
            return $client->channel();

        })->then(function (Channel $ch) use ($loop, &$channel, &$returnedMessage, &$returnedFrame) {
            $channel = $ch;

            $channel->addReturnListener(function (Message $message, MethodBasicReturnFrame $frame) use ($loop, &$returnedMessage, &$returnedFrame) {
                $returnedMessage = $message;
                $returnedFrame = $frame;
                $loop->stop();
            });

            return $channel->publish("xxx", [], "", "404", true);
        })->done();

        $loop->run();

        $this->assertNotNull($returnedMessage);
        $this->assertInstanceOf("Bunny\\Message", $returnedMessage);
        $this->assertEquals("xxx", $returnedMessage->content);
        $this->assertEquals("", $returnedMessage->exchange);
        $this->assertEquals("404", $returnedMessage->routingKey);
    }

}
