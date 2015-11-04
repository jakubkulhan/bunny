<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'channel.close-ok' (class #20, method #41) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodChannelCloseOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_CLOSE_OK);
    }

}
