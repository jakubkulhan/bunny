<?php

declare(strict_types=1);

namespace Bunny\Test\Helper;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Configuration;
use Bunny\Helper;
use Bunny\Message;
use Bunny\Test\Library\ClientHelper;
use Bunny\Test\Library\Environment;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use WyriHaximus\React\PHPUnit\RunTestsInFibersTrait;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;

class AsyncTest extends TestCase
{
    use RunTestsInFibersTrait;

    private ClientHelper $helper;

    public function setUp(): void
    {
        parent::setUp();

        $this->helper = new ClientHelper();
    }

    public function testPublish(): void
    {
        /**
         * @var Deferred<string> $deferred
         */
        $deferred = new Deferred();
        $c = $this->helper->createClient();
        $a = Helper::async(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', false, false, false, true);
        self::assertTrue($c->isConnected());
        $ch->consume(static function (Message $msg, Channel $ch, Client $c) use ($deferred): void {
            $deferred->resolve($msg->content);
        });
        self::assertTrue($c->isConnected());
        $a->publish('hi', [], '', 'test_queue');
        self::assertEquals('hi', await($deferred->promise()));

        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    public function testGet(): void
    {
        $asyncHelper = Helper::async(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare('get_test');
        $channel->publish('.', [], '', 'get_test');

        $message1 = $asyncHelper->get('get_test', true);
        self::assertNotNull($message1);
        self::assertInstanceOf(Message::class, $message1);
        self::assertEquals($message1->exchange, '');
        self::assertEquals($message1->content, '.');

        $message2 = $asyncHelper->get('get_test', true);
        self::assertNull($message2);

        $channel->publish('..', [], '', 'get_test');

        $asyncHelper->get('get_test');
        $client->disconnect();

        await(sleep(5));

        $client->connect();

        $channel  = $client->channel();
        $message3 = $asyncHelper->get('get_test');
        self::assertNotNull($message3);
        self::assertInstanceOf(Message::class, $message3);
        self::assertEquals($message3->exchange, '');
        self::assertEquals($message3->content, '..');

        $channel->ack($message3);

        $client->disconnect();

        await(sleep(5));

        self::assertFalse($client->isConnected());
    }

    public function testIterate(): void
    {
        self::expectOutputString('scscscsc');
        $message = 'hi';
        $asyncHelper = Helper::async(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));
        $c = $this->helper->createClient();
        $expectedMessages = [];
        $messages = [];

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', false, false, false, true);
        self::assertTrue($c->isConnected());

        Loop::futureTick(async(static function () use ($ch, &$expectedMessages, $message): void {
            for ($i = 0; $i <= 1337; $i++) {
                $expectedMessages[$i] = $message;
                $ch->publish($message, [], '', 'test_queue');
            }
        }));

        Loop::futureTick(async(static function () use ($asyncHelper, &$message): void {
            $count = 0;
            foreach ($asyncHelper->iterate('test_queue') as $message) {
                $count++;
                $messages[$count] = $message->content;

                if ($count === 1337) {
                    break;
                }
            }
        }));

        self::assertSame($expectedMessages, $messages);

        await(sleep(0.1));
        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }
}
