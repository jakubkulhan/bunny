<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.close' (class #10, method #50) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionCloseFrame extends MethodFrame
{

    /** @var int */
    public $replyCode;

    /** @var string */
    public $replyText = '';

    /** @var int */
    public $closeClassId;

    /** @var int */
    public $closeMethodId;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_CLOSE);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
