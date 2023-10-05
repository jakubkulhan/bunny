<?php
namespace Bunny;

/**
 * State of AMQP channel.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class ChannelStateEnum
{

    /**
     * Channel is ready to receive messages.
     */
    const READY = 1;

    /**
     * Channel got method that is followed by header/content frames and now waits for header frame.
     */
    const AWAITING_HEADER = 2;

    /**
     * Channel got method and header frame and now waits for body frame.
     */
    const AWAITING_BODY = 3;

    /**
     * An error occurred on channel.
     */
    const ERROR = 4;

    /**
     * Channel is being closed.
     */
    const CLOSING = 5;

    /**
     * Channel has received channel.close-ok frame.
     */
    const CLOSED = 6;

}
