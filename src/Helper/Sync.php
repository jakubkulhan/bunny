<?php

declare(strict_types=1);

namespace Bunny\Helper;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use Throwable;
use function React\Async\async;
use function React\Async\await;

final class Sync
{
    private const TIMEOUT = PublishMessage::TIMEOUT;

    public function __construct(
        private readonly Async $async,
    ) {
    }

    /**
     * Open connection, published message to given exchange, and close the connection before returning.
     *
     * @param array<string,mixed> $headers
     *
     * @return int|false
     */
    public function publish(
        string $body,
        array $headers = [],
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        float $timeout = self::TIMEOUT,
    ): int|bool {
        $deferred = new Deferred();
        Loop::futureTick(async(function () use ($body, $headers, $exchange, $routingKey, $mandatory, $immediate, $timeout, $deferred): void {
            try {
                $result = $this->async->publish($body, $headers, $exchange, $routingKey, $mandatory, $immediate, $timeout);

                Loop::futureTick(static function (): void {
                    Loop::stop();
                });

                $deferred->resolve($result);
            } catch (Throwable $exception) {
                Loop::futureTick(static function (): void {
                    Loop::stop();
                });

                $deferred->reject($exception);
            }
        }));

        Loop::run();

        return await($deferred->promise());
    }
}
