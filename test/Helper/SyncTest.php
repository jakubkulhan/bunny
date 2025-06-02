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
use React\Promise\Deferred;
use WyriHaximus\React\PHPUnit\RunTestsInFibersTrait;
use function React\Async\await;

class SyncTest extends TestCase
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
        $a = Helper::sync(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));

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
}
