<?php

declare(strict_types=1);

namespace Bunny\Test\Library;

use Bunny\Async\Client;
use React\EventLoop\LoopInterface;

final class AsynchronousClientHelper extends AbstractClientHelper
{
    /**
     * @param LoopInterface $loop
     * @param array|null $options
     *
     * @return Client
     */
    public function createClient(LoopInterface $loop, array $options = null): Client
    {
        $options = $options ?? $this->getDefaultOptions();

        return new Client($loop, $options);
    }

    /**
     * @return array
     */
    public function getDefaultOptions(): array
    {
        $options = [];

        $options = array_merge($options, $this->parseAmqpUri(getenv('TEST_RABBITMQ_CONNECTION_URI')));

        return $options;
    }
}
