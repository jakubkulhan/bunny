<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.publish' (class #60, method #40) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicPublishFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $exchange = '';

    /** @var string */
    public $routingKey = '';

    /** @var boolean */
    public $mandatory = false;

    /** @var boolean */
    public $immediate = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_PUBLISH);
    }

}
