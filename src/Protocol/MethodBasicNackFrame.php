<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.nack' (class #60, method #120) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicNackFrame extends MethodFrame
{

    /** @var int */
    public $deliveryTag = 0;

    /** @var bool */
    public $multiple = false;

    /** @var bool */
    public $requeue = true;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_NACK);
    }

}
