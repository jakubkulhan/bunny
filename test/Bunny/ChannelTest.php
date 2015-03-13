<?php
namespace Bunny;

class ChannelTest extends \PHPUnit_Framework_TestCase
{

    public function testClose()
    {
        $c = new Client();
        $c->connect();
        $this->assertInstanceOf("Bunny\\Protocol\\MethodChannelCloseOkFrame", $c->channel()->close());
        $c->disconnect();
    }

    public function testExchangeDeclare()
    {
        $ch = (new Client())->connect()->channel();
        $ch->exchangeDeclare("test_exchange", "direct", false, false, true);
        $ch->getClient()->disconnect();
    }

    public function testQueueDeclare()
    {
        $ch = (new Client())->connect()->channel();
        $ch->queueDeclare("test_queue", false, false, false, true);
        $ch->getClient()->disconnect();
    }

    public function testQueueBind()
    {
        $ch = (new Client())->connect()->channel();
        $ch->exchangeDeclare("test_exchange", "direct", false, false, true);
        $ch->queueDeclare("test_queue", false, false, false, true);
        $ch->queueBind("test_queue", "test_exchange");
        $ch->getClient()->disconnect();
    }

    public function testPublish()
    {
        $ch = (new Client())->connect()->channel();
        $ch->publish("test publish", []);
        $ch->getClient()->disconnect();
    }

    public function testConsume()
    {
        $ch = (new Client())->connect()->channel();
        $ch->queueDeclare("test_queue", false, false, false, true);
        $ch->consume(function (Message $msg, Channel $ch, Client $c) {
            $this->assertEquals("hi", $msg->content);
            $c->stop();
        });
        $ch->publish("hi", [], "", "test_queue");
        $ch->getClient()->run();
        $ch->getClient()->disconnect();
    }

    public function testRun()
    {
        $ch = (new Client())->connect()->channel();
        $ch->queueDeclare("test_queue", false, false, false, true);
        $ch->publish("hi again", [], "", "test_queue");
        $ch->run(function (Message $msg, Channel $ch, Client $c) {
            $this->assertEquals("hi again", $msg->content);
            $c->stop();
        });
        $ch->getClient()->disconnect();
    }

    public function testHeaders()
    {
        $ch = (new Client())->connect()->channel();
        $ch->queueDeclare("test_queue", false, false, false, true);
        $ch->publish("<b>hi html</b>", ["content-type" => "text/html"], "", "test_queue");
        $ch->run(function (Message $msg, Channel $ch, Client $c) {
            $this->assertTrue($msg->hasHeader("content-type"));
            $this->assertEquals("text/html", $msg->getHeader("content-type"));
            $this->assertEquals("<b>hi html</b>", $msg->content);
            $c->stop();
        });
        $ch->getClient()->disconnect();
    }

}
