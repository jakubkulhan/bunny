<?php

declare(strict_types=1);

namespace Bunny;

use Closure;
use InvalidArgumentException;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use SensitiveParameter;
use function array_key_exists;
use function count;
use function is_array;
use function ltrim;
use function parse_str;
use function parse_url;
use function sprintf;
use function strlen;

final class Configuration
{
    public readonly string $uri;
    public readonly ConnectorInterface $connector;

    public function __construct(
        string $host = Defaults::HOST,
        int $port = Defaults::PORT,
        public readonly string $vhost = Defaults::VHOST,
        #[SensitiveParameter]
        public readonly string $user = Defaults::USER,
        #[SensitiveParameter]
        public readonly string $password = Defaults::PASSWORD,
        public readonly float $timeout = Defaults::TIMEOUT,
        public readonly float $heartbeat = Defaults::HEARTBEAT,
        public readonly ?Closure $heartbeatCallback = Defaults::HEARTBEAT_CALLBACK,
        /**
         * @var array<string, mixed>
         */
        public readonly array $tls = Defaults::TLS,
        /**
         * @var array<string, mixed>
         */
        public readonly array $clientProperties = Defaults::CLIENT_PROPERTIES,
        ?ConnectorInterface $connector = Defaults::CONNECTOR,
    ) {
        $streamScheme = 'tcp';
        if (count($tls) > 0) {
            $streamScheme = 'tls';
        }

        $this->uri = sprintf('%s://%s:%s', $streamScheme, $host, $port);

        $this->connector = $connector ?? new Connector([
            'timeout' => $timeout,
            'tls' => $tls,
        ]);
    }

    public static function fromDSN(
        #[SensitiveParameter]
        string $dsn,
        ?Closure $heartbeatCallback = Defaults::HEARTBEAT_CALLBACK,
        ?ConnectorInterface $connector = Defaults::CONNECTOR,
    ): Configuration {
        $chunks = parse_url($dsn);

        if (!is_array($chunks)) {
            throw new InvalidArgumentException(sprintf('Invalid DSN: %s', $dsn));
        }

        $query = [];
        parse_str($chunks['query'] ?? '', $query);

        return new self(
            host: $chunks['host'] ?? Defaults::HOST,
            port: $chunks['port'] ?? Defaults::PORT,
            vhost: array_key_exists('path', $chunks) && strlen(ltrim($chunks['path'], '/')) > 0 ? ltrim($chunks['path'], '/') : Defaults::VHOST,
            user: $chunks['user'] ?? Defaults::USER,
            password: $chunks['pass'] ?? Defaults::PASSWORD,
            timeout: array_key_exists('timeout', $query) ? (float) $query['timeout'] : Defaults::TIMEOUT,
            heartbeat: array_key_exists('heartbeat', $query) ? (float) $query['heartbeat'] : Defaults::HEARTBEAT,
            heartbeatCallback: $heartbeatCallback ?? Defaults::HEARTBEAT_CALLBACK,
            tls: array_key_exists('tls', $query) ? $query['tls'] : Defaults::TLS, /** @phpstan-ignore argument.type */
            clientProperties: array_key_exists('client_properties', $query) ? $query['client_properties'] : Defaults::CLIENT_PROPERTIES, /** @phpstan-ignore argument.type */
            connector: $connector ?? Defaults::CONNECTOR,
        );
    }
}
