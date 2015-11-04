<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'exchange.unbind' (class #40, method #40) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodExchangeUnbindFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $destination;

    /** @var string */
    public $source;

    /** @var string */
    public $routingKey = '';

    /** @var boolean */
    public $nowait = false;

    /** @var array */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_UNBIND);
    }

}
