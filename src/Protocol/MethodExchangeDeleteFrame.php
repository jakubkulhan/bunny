<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'exchange.delete' (class #40, method #20) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodExchangeDeleteFrame extends MethodFrame
{

    /** @var string */
    public $exchange;

    /** @var int */
    public $reserved1 = 0;

    /** @var bool */
    public $ifUnused = false;

    /** @var bool */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DELETE);
    }

}
