<?php
namespace Bunny;

/**
 * Mode of AMQP channel (normal, transactional, confirm mode).
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class ChannelModeEnum
{

    /**
     * Regular AMQP guarantees of published messages delivery.
     */
    const REGULAR = 1;

    /**
     * Messages are published after 'tx.commit'.
     */
    const TRANSACTIONAL = 2;

    /**
     * Broker sends asynchronously 'basic.ack's for delivered messages.
     */
    const CONFIRM = 3;



}
