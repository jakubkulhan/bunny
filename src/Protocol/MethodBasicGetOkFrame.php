<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.get-ok' (class #60, method #71) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicGetOkFrame extends MethodFrame
{

    /** @var int */
    public $deliveryTag;

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey;

    /** @var int */
    public $messageCount;

    /** @var bool */
    public $redelivered = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_GET_OK);
    }

}
