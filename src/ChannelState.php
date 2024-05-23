<?php

declare(strict_types=1);

namespace Bunny;

/**
 * State of AMQP channel.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
enum ChannelState
{
    /**
     * Channel is ready to receive messages.
     */
    case Ready;

    /**
     * Channel got method that is followed by header/content frames and now waits for header frame.
     */
    case AwaitingHeader;

    /**
     * Channel got method and header frame and now waits for body frame.
     */
    case AwaitingBody;

    /**
     * An error occurred on channel.
     */
    case Error;

    /**
     * Channel is being closed.
     */
    case Closing;

    /**
     * Channel has received channel.close-ok frame.
     */
    case Closed;
}
