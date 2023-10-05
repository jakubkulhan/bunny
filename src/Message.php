<?php

declare(strict_types=1);

namespace Bunny;

/**
 * Convenience crate for transferring messages through app.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class Message
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public string|null $consumerTag,
        public int|null $deliveryTag,
        public bool|null $redelivered,
        public string $exchange,
        public string $routingKey,
        public array $headers,
        public string $content,
    )
    {
    }

    /**
     * Returns header or default value.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, $this->headers)) {
            return $this->headers[$name];
        } else {
            return $default;
        }
    }

    /**
     * Returns TRUE if message has given header.
     *
     * @param string $name
     * @return boolean
     */
    public function hasHeader(string $name): bool
    {
        return array_key_exists($name, $this->headers);
    }

}
