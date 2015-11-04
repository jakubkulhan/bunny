<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.qos' (class #60, method #10) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicQosFrame extends MethodFrame
{

    /** @var int */
    public $prefetchSize = 0;

    /** @var int */
    public $prefetchCount = 0;

    /** @var boolean */
    public $global = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_QOS);
    }

}
