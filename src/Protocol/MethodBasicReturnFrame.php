<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.return' (class #60, method #50) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicReturnFrame extends MethodFrame
{

    /** @var int */
    public $replyCode;

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey;

    /** @var string */
    public $replyText = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RETURN);
    }

}
