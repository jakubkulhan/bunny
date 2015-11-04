<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.cancel' (class #60, method #30) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicCancelFrame extends MethodFrame
{

    /** @var string */
    public $consumerTag;

    /** @var boolean */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_CANCEL);
    }

}
