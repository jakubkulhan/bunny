<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.recover-async' (class #60, method #100) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicRecoverAsyncFrame extends MethodFrame
{

    /** @var boolean */
    public $requeue = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RECOVER_ASYNC);
    }

}
