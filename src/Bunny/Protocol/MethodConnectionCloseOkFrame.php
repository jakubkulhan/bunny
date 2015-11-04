<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.close-ok' (class #10, method #51) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionCloseOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_CLOSE_OK);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
