<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'queue.declare-ok' (class #50, method #11) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodQueueDeclareOkFrame extends MethodFrame
{

    /** @var string */
    public $queue;

    /** @var int */
    public $messageCount;

    /** @var int */
    public $consumerCount;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DECLARE_OK);
    }

}
