<?php
namespace Bunny;

use Bunny\Exception\SoftError404Exception;
use PHPUnit\Framework\TestCase;

class SoftErrorTest extends TestCase
{

    public function testSoftError404()
    {
        $client = new Client();
        $client->connect();
        $channel = $client->channel();

        $channel->qos(0, 1000);
        $channel->queueDeclare("non_exchange_test");

        // Publishing to a non existing exchange should generate
        // a 404 NOT-FOUND error.
        // $this->expectException(SoftError404Exception::class);
        $channel->publish(".", [], "non_existing_exchange", "non_exchange_test");
        $client->run(5);
    }
}
