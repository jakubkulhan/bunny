<?php
namespace Bunny;

use Bunny\Async\Client;
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
        });

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
        });

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
        }, function ($e) use ($loop) {
            throw $e;
        });

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
        });

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
        });

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
        });

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

                    $client->disconnect()->then(function () use ($loop) {
                        $loop->stop();
                    });

                }, "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
                $channel->publish(".", [], "", "disconnect_test"),
            ]);
        });

        $loop->run();

        // all messages should be processed
        $this->assertEquals(1, $processed);
    }

}
