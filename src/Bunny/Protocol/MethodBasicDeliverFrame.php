<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.deliver' (class #60, method #60) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicDeliverFrame extends MethodFrame
{

    /** @var string */
    public $consumerTag;

    /** @var int */
    public $deliveryTag;

    /** @var boolean */
    public $redelivered = false;

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_DELIVER);
    }

}
