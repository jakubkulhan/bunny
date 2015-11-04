<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'exchange.declare-ok' (class #40, method #11) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodExchangeDeclareOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DECLARE_OK);
    }

}
