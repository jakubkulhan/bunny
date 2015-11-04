<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.ack' (class #60, method #80) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicAckFrame extends MethodFrame
{

    /** @var int */
    public $deliveryTag = 0;

    /** @var boolean */
    public $multiple = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_ACK);
    }

}
