<?php

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Client;
use Bunny\ClientState;
use ReflectionProperty;

/**
 * Treats Connecting as not connected. This matches the expectation that a second
 * channel() call during connect must wait, not invoke connect() again.
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc (test double for Client)
 */
final class ConnectingIsNotConnectedClient extends Client
{
    public function isConnected(): bool
    {
        $state = new ReflectionProperty(Client::class, 'state');

        return $state->getValue($this) === ClientState::Connected;
    }
}
