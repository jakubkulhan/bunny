<?php

declare(strict_types=1);

namespace Bunny\Test\Library;

use Bunny\Client;
use React\Promise\Promise;

final class SynchronousClientHelper extends AbstractClientHelper
{
    /**
     * @param array|null $options
     *
     * @return Client
     */
    public function createClient(array $options = null): Client
    {
        $options = array_merge($this->getDefaultOptions(), $options ?? []);

        return new Client($options);
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
