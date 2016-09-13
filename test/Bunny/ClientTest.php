<?php
namespace Bunny;

use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicReturnFrame;

class ClientTest extends \PHPUnit_Framework_TestCase
{

    public function testConnectAsGuest()
    {
        $client = new Client();
        $client->connect();
        $client->disconnect();
    }

    public function testConnectAuth()
    {
        $client = new Client([
            "user" => "testuser",
            "password" => "testpassword",
            "vhost" => "testvhost",
        ]);
        $client->connect();
        $client->disconnect();
    }

    public function testConnectFailure()
    {
        $this->setExpectedException("Bunny\\Exception\\ClientException");

        $client = new Client([
            "user" => "testuser",
            "password" => "testpassword",
            "vhost" => "/"
        ]);

        $client->connect();
    }

    public function testOpenChannel()
    {
        $client = new Client();
        $this->assertInstanceOf("Bunny\\Channel", $client->connect()->channel());
        $client->disconnect();
    }

    public function testOpenMultipleChannel()
    {
        $client = new Client();
        $client->connect();
        $this->assertInstanceOf("Bunny\\Channel", $ch1 = $client->channel());
        $this->assertInstanceOf("Bunny\\Channel", $ch2 = $client->channel());
        $this->assertNotEquals($ch1->getChannelId(), $ch2->getChannelId());
        $this->assertInstanceOf("Bunny\\Channel", $ch3 = $client->channel());
        $this->assertNotEquals($ch1->getChannelId(), $ch3->getChannelId());
        $this->assertNotEquals($ch2->getChannelId(), $ch3->getChannelId());
        $client->disconnect();
    }

    public function testRunMaxSeconds()
    {
        $client = new Client();
        $client->connect();
        $s = microtime(true);
        $client->run(1.0);
        $e = microtime(true);
        $this->assertLessThan(2.0, $e - $s);
    }

    public function testDisconnectWithBufferedMessages()
    {
        $client = new Client();
        $client->connect();
        $channel = $client->channel();

        $processed = 0;

        $channel->qos(0, 1000);
        $channel->queueDeclare("disconnect_test");
        $channel->consume(function (Message $message, Channel $channel) use ($client, &$processed) {
            $channel->ack($message);
            ++$processed;
            $client->disconnect()->done(function () use ($client) {
                $client->stop();
            });
        });
        $channel->publish(".", [], "", "disconnect_test");
        $channel->publish(".", [], "", "disconnect_test");
        $channel->publish(".", [], "", "disconnect_test");

        $client->run(5);

        $this->assertEquals(1, $processed);
    }

    public function testGet()
    {
        $client = new Client();
        $client->connect();
        $channel  = $client->channel();

        $channel->queueDeclare("get_test");
        $channel->publish(".", [], "", "get_test");

        $message1 = $channel->get("get_test", true);
        $this->assertNotNull($message1);
        $this->assertInstanceOf("Bunny\\Message", $message1);
        $this->assertEquals($message1->exchange, "");
        $this->assertEquals($message1->content, ".");

        $message2 = $channel->get("get_test", true);
        $this->assertNull($message2);

        $channel->publish("..", [], "", "get_test");

        $channel->get("get_test");
        $client->disconnect()->then(function () use ($client) {
            $client->connect();

            $channel  = $client->channel();
            $message3 = $channel->get("get_test");
            $this->assertNotNull($message3);
            $this->assertInstanceOf("Bunny\\Message", $message3);
            $this->assertEquals($message3->exchange, "");
            $this->assertEquals($message3->content, "..");

            $channel->ack($message3);

            return $client->disconnect();

        })->then(function () use ($client) {
            $client->stop();
        })->done();

        $client->run(5);
    }

    public function testReturn()
    {
        $client = new Client();
        $client->connect();
        $channel  = $client->channel();

        /** @var Message $returnedMessage */
        $returnedMessage = null;
        /** @var MethodBasicReturnFrame $returnedFrame */
        $returnedFrame = null;
        $channel->addReturnListener(function (Message $message, MethodBasicReturnFrame $frame) use ($client, &$returnedMessage, &$returnedFrame) {
            $returnedMessage = $message;
            $returnedFrame = $frame;
            $client->stop();
        });

        $channel->publish("xxx", [], "", "404", true);

        $client->run(1);

        $this->assertNotNull($returnedMessage);
        $this->assertInstanceOf("Bunny\\Message", $returnedMessage);
        $this->assertEquals("xxx", $returnedMessage->content);
        $this->assertEquals("", $returnedMessage->exchange);
        $this->assertEquals("404", $returnedMessage->routingKey);
    }

    public function testTxs()
    {
        $client = new Client();
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
    }

    public function testTxSelectCannotBeCalledMultipleTimes()
    {
        $this->setExpectedException("Bunny\\Exception\\ChannelException");

        $client = new Client();
        $client->connect();
        $channel = $client->channel();

        $channel->txSelect();
        $channel->txSelect();
    }

    public function testConfirmMode()
    {
        $client = new Client();
        $client->connect();
        $channel = $client->channel();

        $deliveryTag = null;
        $channel->confirmSelect(function (MethodBasicAckFrame $frame) use (&$deliveryTag, $client) {
            if ($frame->deliveryTag === $deliveryTag) {
                $deliveryTag = null;
                $client->stop();
            }
        });

        $deliveryTag = $channel->publish(".");

        $client->run(1);

        $this->assertNull($deliveryTag);
    }

    public function testEmptyMessage()
    {
        $client = new Client();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare("empty_body_message_test");

        $channel->publish("", [], "", "empty_body_message_test");
        $message = $channel->get("empty_body_message_test", true);
        $this->assertNotNull($message);
        $this->assertEquals("", $message->content);

        $processed = 0;
        $channel->consume(function (Message $message, Channel $channel) use ($client, &$processed) {
            $this->assertEmpty($message->content);
            $channel->ack($message);
            if (++$processed === 2) {
                $client->disconnect()->done(function () use ($client) {
                    $client->stop();
                });
            }
        }, "empty_body_message_test");

        $channel->publish("", [], "", "empty_body_message_test");
        $channel->publish("", [], "", "empty_body_message_test");

        $client->run(1);
    }

}
