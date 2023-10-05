<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'exchange.declare' (class #40, method #10) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodExchangeDeclareFrame extends MethodFrame
{

    /** @var string */
    public $exchange;

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $exchangeType = 'direct';

    /** @var bool */
    public $passive = false;

    /** @var bool */
    public $durable = false;

    /** @var bool */
    public $autoDelete = false;

    /** @var bool */
    public $internal = false;

    /** @var bool */
    public $nowait = false;

    /** @var array<mixed> */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DECLARE);
    }

}
