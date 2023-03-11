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
     * Disconnects a synchronous client in a reliable way
     *
     * Calling just `Client::disconnect` instead of running the code in this
     * method does only work as (probably) expected (ie. actually close the
     * connection to the broker) if the client does not have any open channels.
     * Otherwise, `Client::disconnect` waits for `Channel::close` on open
     * channels to complete which is promise-based and needs a running event
     * loop (`Client::run`) to be able to fulfill its promise. If there is no
     * running event loop after calling `Client::disconnect`, the client will
     * stay connected until its destructor is called or the connection is
     * closed by the broker (eg. because of missing a heartbeat event).
     *
     * The code in this method contains the same logic as `Client::__destruct`.
     *
     * See this discussion for more details:
     *
     * - https://github.com/jakubkulhan/bunny/issues/93
     *
     * @param Client $client
     *
     * @return void
     */
    public function disconnectClientWithEventLoop(Client $client)
    {
        if (!$client->isConnected()) {
            return;
        }

        /** @var Promise $disconnectPromise */
        $disconnectPromise = $client->disconnect();

        $disconnectPromise->done(
            function () use ($client) {
                $client->stop();
            }
        );

        if (!$client->isConnected()) {
            return;
        }

        $client->run();
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
