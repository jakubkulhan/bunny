<?php

declare(strict_types=1);

namespace Bunny\Test\Library;

use Bunny\Async\Client;

final class AsynchronousClientHelper extends AbstractClientHelper
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
