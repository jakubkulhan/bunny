<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'channel.flow-ok' (class #20, method #21) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodChannelFlowOkFrame extends MethodFrame
{

    /** @var boolean */
    public $active;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_FLOW_OK);
    }

}
