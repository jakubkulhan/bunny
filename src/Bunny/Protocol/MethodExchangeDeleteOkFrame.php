<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'exchange.delete-ok' (class #40, method #21) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodExchangeDeleteOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DELETE_OK);
    }

}
