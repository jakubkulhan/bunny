<?php

declare(strict_types=1);

namespace Bunny\Test;

use Evenement\EventEmitterTrait;
use React\Socket\ConnectionInterface;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface;

// phpcs:disable
final class MockConnectionInterface implements ConnectionInterface {
    use EventEmitterTrait;

    /**
     * @return string
     */
    public function getRemoteAddress()
    {
        return '127.0.0.1:666';
    }

    /**
     * @return string
     */
    public function getLocalAddress()
    {
        return '127.0.0.1:666';
    }

    public function isReadable()
    {
        return true;
    }

    public function pause()
    {
        // No-op
    }

    public function resume()
    {
        // No-op
    }

    /**
     * @param array<mixed> $options
     */
    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        return new ThroughStream();
    }

    public function close()
    {
        // No-op
    }

    /**
     * @retrun bool
     */
    public function isWritable()
    {
        return true;
    }

    /**
     * @param string $data
     *
     * @retrun bool
     */
    public function write($data)
    {
        return false;
    }

    /**
     * @param ?string $data
     */
    public function end($data = null)
    {
        // No-op
    }
}
// phpcs:enable
