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

    private string $buffer = '';

    private bool $fullBuffer;

    public function __construct(bool $fullBuffer = false)
    {
        $this->fullBuffer = $fullBuffer;
    }

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
        $this->buffer .= $data;

        return !$this->fullBuffer;
    }

    /**
     * @param ?string $data
     */
    public function end($data = null)
    {
        $this->buffer .= $data;
    }

    public function getWrittenData(): string
    {
        return $this->buffer;
    }

    public function clearWrittenData(): void
    {
        $this->buffer = '';
    }
}
// phpcs:enable
