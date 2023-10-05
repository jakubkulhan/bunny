<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.blocked' (class #10, method #60) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionBlockedFrame extends MethodFrame
{

    /** @var string */
    public $reason = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_BLOCKED);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
