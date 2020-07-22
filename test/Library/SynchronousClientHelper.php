<?php

declare(strict_types=1);

namespace Bunny\Test\Library;

use Bunny\Client;
use React\Promise\Promise;

final class SynchronousClientHelper
{
    /**
     * @param array|null $options
     *
     * @return Client
     */
    public function createClient(array $options = null): Client
    {
        $options = $options ?? $this->getDefaultOptions();

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
        $uri = getenv('TEST_RABBITMQ_CONNECTION_URI');

        $uriComponents = parse_url($uri);

        if (
            !isset($uriComponents['scheme'])
            || !in_array($uriComponents['scheme'], ['amqp', 'amqps'])
        ) {
            throw new \RuntimeException(
                sprintf(
                    'URI scheme must be "amqp" or "amqps". URI given: "%s"',
                    $uri
                )
            );
        }

        $options = [];

        if (isset($uriComponents['host'])) {
            $options['host'] = $uriComponents['host'];
        }

        if (isset($uriComponents['port'])) {
            $options['port'] = $uriComponents['port'];
        }

        if (isset($uriComponents['user'])) {
            $options['user'] = $uriComponents['user'];
        }

        if (isset($uriComponents['pass'])) {
            $options['password'] = $uriComponents['pass'];
        }

        if (isset($uriComponents['path'])) {
            $vhostCandidate = $uriComponents['path'];

            if (strpos($vhostCandidate, '/') === 0) {
                $vhostCandidate = substr($vhostCandidate, 1);
            }

            if (strpos($vhostCandidate, '/') !== false) {
                throw new \RuntimeException(
                    sprintf(
                        'An URI path component that is a valid vhost may not contain unescaped "/" characters. URI given: "%s"',
                        $uri
                    )
                );
            }

            $vhostCandidate = rawurldecode($vhostCandidate);

            $options['vhost'] = $vhostCandidate;
        }

        return $options;
    }
}
