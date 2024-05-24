<?php

declare(strict_types=1);

namespace Bunny;

/**
 * Mode of AMQP channel (normal, transactional, confirm mode).
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
enum ChannelMode
{

    /**
     * Regular AMQP guarantees of published messages delivery.
     */
    case Regular;

    /**
     * Messages are published after 'tx.commit'.
     */
    case Transactional;

    /**
     * Broker sends asynchronously 'basic.ack's for delivered messages.
     */
    case Confirm;
}
