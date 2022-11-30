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
        $options = array_merge($this->getDefaultOptions(), $options ?? []);

        return new Client($loop, $options);
    }

    /**
     * @return array
     */
    public function getDefaultOptions(): array
    {
        $options = [];

        $options = array_merge($options, parseAmqpUri(Environment::getTestRabbitMqConnectionUri()));

        return $options;
    }
}
