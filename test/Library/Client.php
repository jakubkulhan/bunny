<?php

declare(strict_types=1);

namespace Bunny\Test\Library;

use Bunny\Client as BunnyClient;
use function array_merge;

final class Client
{
    /**
     * @param array<string, mixed>|null $options
     */
    public static function createClient(?array $options = null): BunnyClient
    {
        $options = array_merge(self::getDefaultOptions(), $options ?? []);

        return new BunnyClient($options);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDefaultOptions(): array
    {
        $options = ['heartbeat' => 1.0];

        $options = array_merge($options, parseAmqpUri(Environment::getTestRabbitMqConnectionUri()));

        return $options;
    }
}
