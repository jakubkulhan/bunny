<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.consume-ok' (class #60, method #21) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicConsumeOkFrame extends MethodFrame
{

    /** @var string */
    public $consumerTag;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_CONSUME_OK);
    }

}
