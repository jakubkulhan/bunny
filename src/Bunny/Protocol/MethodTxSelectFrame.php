<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'tx.select' (class #90, method #10) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodTxSelectFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_TX, Constants::METHOD_TX_SELECT);
    }

}
