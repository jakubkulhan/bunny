<?php

declare(strict_types=1);

namespace Bunny\Test\Helper;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Configuration;
use Bunny\Helper;
use Bunny\Helper\Async;
use Bunny\Helper\IterableSubscription;
use Bunny\Message;
use Bunny\Test\Library\ClientHelper;
use Bunny\Test\Library\Environment;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use RuntimeException;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;
use function React\Promise\race;
use function array_fill;
use function count;

class AsyncTest extends TestCase
{
    private ClientHelper $helper;

    public function setUp(): void
    {
        parent::setUp();

        $this->helper = new ClientHelper();
    }

    public function testPublish(): void
    {
        $this->runInFiber(function (): void {
            $this->publish();
        });
    }

    private function publish(): void
    {
        /**
         * @var Deferred<string> $deferred
         */
        $deferred = new Deferred();
        $c = $this->helper->createClient();
        $a = Helper::async(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));

        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', durable: true, autoDelete: true);
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
        $this->runInFiber(function (): void {
            $this->get();
        });
    }

    private function get(): void
    {
        $asyncHelper = Helper::async(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));
        $client = $this->helper->createClient();
        $client->connect();
        $channel = $client->channel();

        $channel->queueDeclare('get_test', durable: true);
        $channel->publish('.', [], '', 'get_test');

        $message1 = $asyncHelper->get('get_test', true);
        self::assertNotNull($message1);
        self::assertInstanceOf(Message::class, $message1);
        self::assertEquals('', $message1->exchange);
        self::assertEquals('.', $message1->content);

        $message2 = $asyncHelper->get('get_test', true);
        self::assertNull($message2);

        $client->disconnect();
        self::assertFalse($client->isConnected());
    }

    public function testBreakDisconnectsClient(): void
    {
        $this->runInFiber(function (): void {
            $this->breakDisconnectsClient();
        });
    }

    public function testDestroyingSubscriptionDisconnectsClient(): void
    {
        $this->runInFiber(function (): void {
            $client = $this->helper->createClient();
            $channel = $client->connect()->channel();
            $channel->queueDeclare('iterate_destroy_test', durable: true, autoDelete: true);

            $subscription = new IterableSubscription($client, 'iterate_destroy_test', noAck: true);
            self::assertTrue($client->isConnected());

            unset($subscription);
            await(sleep(0.1));

            self::assertFalse($client->isConnected());
        });
    }

    public function testBreakResolvesWaitingSubscription(): void
    {
        $this->runInFiber(function (): void {
            $client = $this->helper->createClient();
            $channel = $client->connect()->channel();
            $channel->queueDeclare('iterate_wait_test', durable: true, autoDelete: true);

            $subscription = new IterableSubscription($client, 'iterate_wait_test', noAck: true);
            $valid = async(static function () use ($subscription): bool {
                return $subscription->valid();
            })();

            await(sleep(0.01));
            $subscription->break();

            self::assertFalse(await($valid));
            await(sleep(0.1));

            self::assertFalse($client->isConnected());
        });
    }

    private function breakDisconnectsClient(): void
    {
        $client = $this->helper->createClient();
        $channel = $client->connect()->channel();
        $channel->queueDeclare('iterate_disconnect_test', durable: true, autoDelete: true);
        $channel->publish('message', [], '', 'iterate_disconnect_test');

        $subscription = (new Async($client))->iterate('iterate_disconnect_test', noAck: true);
        self::assertTrue($client->isConnected());

        foreach ($subscription as $message) {
            self::assertSame('message', $message->content);
            break;
        }

        unset($subscription);
        await(sleep(0.1));

        self::assertFalse($client->isConnected());
    }

    public function testIterate(): void
    {
        $this->runInFiber(function (): void {
            $this->iterate();
        });
    }

    private function iterate(): void
    {
        $body = 'hi';
        $asyncHelper = Helper::async(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));
        $c = $this->helper->createClient();
        $ch = $c->connect()->channel();
        self::assertTrue($c->isConnected());
        $ch->queueDeclare('test_queue', durable: true, autoDelete: true);
        self::assertTrue($c->isConnected());

        $expectedMessages = array_fill(0, 1337, $body);
        foreach ($expectedMessages as $message) {
            $ch->publish($message, [], '', 'test_queue');
        }

        $subscription = $asyncHelper->iterate('test_queue', noAck: true);
        $messages = [];
        foreach ($subscription as $message) {
            $messages[] = $message->content;

            if (count($messages) === 1337) {
                break;
            }
        }

        unset($subscription);
        await(sleep(0.1));
        self::assertSame($expectedMessages, $messages);
        self::assertTrue($c->isConnected());
        $c->disconnect();
        self::assertFalse($c->isConnected());
    }

    /** @param callable(): void $test */
    private function runInFiber(callable $test): void
    {
        await(race([
            async($test)(),
            sleep(60)->then(static function (): void {
                throw new RuntimeException('Test timed out after 60 seconds');
            }),
        ]));
    }
}
