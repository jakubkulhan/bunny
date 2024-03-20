<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.unblocked' (class #10, method #61) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionUnblockedFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_UNBLOCKED);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
