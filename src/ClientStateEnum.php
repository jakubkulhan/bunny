<?php
namespace Bunny;

/**
 * State of AMQP client.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class ClientStateEnum
{

    /**
     * Client is not connected. Method connect() hasn't been called yet.
     */
    const NOT_CONNECTED = 0;

    /**
     * Client is currently connecting to AMQP server.
     */
    const CONNECTING = 1;

    /**
     * Client is connected and ready to communicate.
     */
    const CONNECTED = 2;

    /**
     * Client is currently disconnecting from AMQP server.
     */
    const DISCONNECTING = 3;

    /**
     * An error has occurred.
     */
    const ERROR = 4;

}
