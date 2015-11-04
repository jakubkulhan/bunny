<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.reject' (class #60, method #90) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicRejectFrame extends MethodFrame
{

    /** @var int */
    public $deliveryTag;

    /** @var boolean */
    public $requeue = true;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_REJECT);
    }

}
