<?php
namespace Bunny;

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
            $client->disconnect()->then(function () use ($client) {
                $client->stop();
            });
        });
        $channel->publish(".", [], "", "disconnect_test");
        $channel->publish(".", [], "", "disconnect_test");
        $channel->publish(".", [], "", "disconnect_test");

        $client->run(5);

        $this->assertEquals(1, $processed);
    }
}
