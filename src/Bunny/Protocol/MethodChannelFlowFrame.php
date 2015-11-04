<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'channel.flow' (class #20, method #20) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodChannelFlowFrame extends MethodFrame
{

    /** @var boolean */
    public $active;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_FLOW);
    }

}
