<?php

declare(strict_types=1);

namespace Bunny\Test\Library;

final class Environment
{
    public static function getSslCa(): string
    {
        return trim(self::getenv('SSL_CA'));
    }

    public static function getSslClientCert(): string
    {
        return trim(self::getenv('SSL_CLIENT_CERT', ''));
    }

    public static function getSslClientKey(): string
    {
        return trim(self::getenv('SSL_CLIENT_KEY', ''));
    }

    public static function getSslPeerName(): string
    {
        return trim(self::getenv('SSL_PEER_NAME'));
    }

    /**
     * Can have the following values:
     *
     * - "client"       -> run all SSL tests, expect peer cert (c.f. rabbitmq.ssl.verify_peer.conf)
     * - "yes"          -> run all SSL tests, do *not* expect peer cert (c.f. rabbitmq.ssl.verify_none.conf)
     * - "no" (default) -> skip SSL-related tests
     */
    public static function getSslTest(): string
    {
        $value = self::getenv('SSL_TEST', 'no');

        switch ($value) {
            case 'client':
            case 'no':
            case 'yes':
                return $value;
        }

        throw new EnvironmentException('SSL_TEST');
    }

    public static function getTestRabbitMqConnectionUri(): string
    {
        return trim(self::getenv('TEST_RABBITMQ_CONNECTION_URI'));
    }

    private static function getenv(string $envVariable, ?string $default = null):string
    {
        $value = getenv($envVariable);

        if ($value === false && $default === null) {
            throw new EnvironmentException($envVariable);
        }

        return $value!==false?$value:$default;
    }
}
