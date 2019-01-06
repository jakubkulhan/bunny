<?php
namespace Bunny\NG;

/**
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
interface RabbitConnectionFactoryInterface
{

    /**
     * Opens a new connection with the factory connection options.
     *
     * Returned connection is not managed by the instance and must be explicitly closed by its `close()` method.
     *
     * @return RabbitConnectionInterface
     */
    public function newConnection(): RabbitConnectionInterface;

}
