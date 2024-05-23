<?php

declare(strict_types=1);

namespace Bunny;

/**
 * State of AMQP client.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
enum ClientState
{

    /**
     * Client is not connected. Method connect() hasn't been called yet.
     */
    case NotConnected;

    /**
     * Client is currently connecting to AMQP server.
     */
    case Connecting;

    /**
     * Client is connected and ready to communicate.
     */
    case Connected;

    /**
     * Client is currently disconnecting from AMQP server.
     */
    case Disconnecting;

    /**
     * An error has occurred.
     */
    case Error;

}
