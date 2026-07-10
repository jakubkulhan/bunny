<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Configuration;
use Bunny\Defaults;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use React\Socket\Connector;

final class ConfigurationTest extends TestCase
{
    /**
     * @return iterable<array{0: string, 1: array<string, mixed>}>
     */
    public static function DSNs(): iterable
    {
        yield [
            'amqp://guest:guest@localhost:5672/',
            [
                'uri' => 'tcp://localhost:5672',
                'vhost' => Defaults::VHOST,
                'user' => 'guest',
                'password' => 'guest',
                'timeout' => Defaults::TIMEOUT,
                'heartbeat' => Defaults::HEARTBEAT,
                'tls' => Defaults::TLS,
                'clientProperties' => Defaults::CLIENT_PROPERTIES,
            ],
        ];

        yield [
            'amqp://guest:guest@localhost:5672/vhost',
            [
                'uri' => 'tcp://localhost:5672',
                'vhost' => 'vhost',
                'user' => 'guest',
                'password' => 'guest',
                'timeout' => Defaults::TIMEOUT,
                'heartbeat' => Defaults::HEARTBEAT,
                'tls' => Defaults::TLS,
                'clientProperties' => Defaults::CLIENT_PROPERTIES,
            ],
        ];

        yield [
            'amqp://guest:guest@localhost:5672/vhost?timeout=123',
            [
                'uri' => 'tcp://localhost:5672',
                'vhost' => 'vhost',
                'user' => 'guest',
                'password' => 'guest',
                'timeout' => 123,
                'heartbeat' => Defaults::HEARTBEAT,
                'tls' => Defaults::TLS,
                'clientProperties' => Defaults::CLIENT_PROPERTIES,
            ],
        ];

        yield [
            'amqp://guest:guest@localhost:5672/vhost?timeout=456&heartbeat=789',
            [
                'uri' => 'tcp://localhost:5672',
                'vhost' => 'vhost',
                'user' => 'guest',
                'password' => 'guest',
                'timeout' => 456,
                'heartbeat' => 789,
                'tls' => Defaults::TLS,
                'clientProperties' => Defaults::CLIENT_PROPERTIES,
            ],
        ];

        yield [
            'amqp://guest:guest@localhost:5672/vhost?tls[cafile]=ca.pem&tls[local_cert]=client.cert&tls[local_pk]=client.key',
            [
                'uri' => 'tls://localhost:5672',
                'vhost' => 'vhost',
                'user' => 'guest',
                'password' => 'guest',
                'timeout' => Defaults::TIMEOUT,
                'heartbeat' => Defaults::HEARTBEAT,
                'tls' => [
                    'cafile'      => 'ca.pem',
                    'local_cert'  => 'client.cert',
                    'local_pk'    => 'client.key',
                ],
                'clientProperties' => Defaults::CLIENT_PROPERTIES,
            ],
        ];

        yield [
            'amqp://guest:guest@localhost:5672/vhost?client_properties[connection_name]=My connection',
            [
                'uri' => 'tcp://localhost:5672',
                'vhost' => 'vhost',
                'user' => 'guest',
                'password' => 'guest',
                'timeout' => Defaults::TIMEOUT,
                'heartbeat' => Defaults::HEARTBEAT,
                'tls' => Defaults::TLS,
                'clientProperties' => ['connection_name' => 'My connection'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expectations
     */
    #[DataProvider('DSNs')]
    public function testFromDSN(string $dsn, array $expectations): void
    {
        $configuration = Configuration::fromDSN($dsn);
        foreach ($expectations as $key => $value) {
            self::assertEquals($value, $configuration->$key);
        }
    }

    #[DataProvider('DSNs')]
    public function testFromDSNWithHeartBeatCallback(string $dsn): void
    {
        $callable = static function (): void {
        };
        $configuration = Configuration::fromDSN($dsn, $callable);

        self::assertSame($callable, $configuration->heartbeatCallback);
    }

    #[DataProvider('DSNs')]
    public function testFromDSNWithConnector(string $dsn): void
    {
        $connector = new Connector();
        $configuration = Configuration::fromDSN($dsn, Defaults::HEARTBEAT_CALLBACK, $connector);

        self::assertSame($connector, $configuration->connector);
    }
}
