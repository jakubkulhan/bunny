<?php

declare(strict_types=1);

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

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey;

    /** @var bool */
    public $redelivered = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_DELIVER);
    }

}
