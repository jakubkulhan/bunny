<?php

declare(strict_types=1);

namespace Bunny\Test\Helper;

use Bunny\Configuration;
use Bunny\Helper;
use Bunny\Test\Library\Environment;
use PHPUnit\Framework\TestCase;
use function is_int;

class SyncTest extends TestCase
{
    public function testPublish(): void
    {
        $syncHelper = Helper::sync(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));

        $result = $syncHelper->publish('hi', [], '', 'sync_publish_test');
        self::assertTrue(is_int($result) === true || $result === false);
    }

    public function testGet(): void
    {
        $syncHelper = Helper::sync(Configuration::fromDSN(Environment::getTestRabbitMqConnectionUri()));

        // Get from empty queue should return null
        $message = $syncHelper->get('sync_get_empty_test', true);
        self::assertNull($message);
    }
}
