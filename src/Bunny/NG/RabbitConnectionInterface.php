<?php
namespace Bunny\NG;

/**
 * Connection to an AMQP 0.9.1 broker.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
interface RabbitConnectionInterface extends ClosableInterface
{

    /**
     * Opens a new channel on the connection.
     *
     * @return RabbitChannelInterface
     */
    public function newChannel(): RabbitChannelInterface;

}
