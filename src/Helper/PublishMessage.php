<?php

declare(strict_types=1);

namespace Bunny\Helper;

final class PublishMessage
{
    public const TIMEOUT = 10.0;

    /**
     * @param array<string,mixed> $headers
     */
    public function __construct(
        public readonly string $body,
        public readonly array $headers = [],
        public readonly string $exchange = '',
        public readonly string $routingKey = '',
        public readonly bool $mandatory = false,
        public readonly bool $immediate = false,
        public readonly float $timeout = self::TIMEOUT,
    ) {
    }
}
