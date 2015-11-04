<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'tx.commit' (class #90, method #20) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodTxCommitFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_TX, Constants::METHOD_TX_COMMIT);
    }

}
