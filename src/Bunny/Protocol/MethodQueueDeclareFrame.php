<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'queue.declare' (class #50, method #10) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodQueueDeclareFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $queue = '';

    /** @var boolean */
    public $passive = false;

    /** @var boolean */
    public $durable = false;

    /** @var boolean */
    public $exclusive = false;

    /** @var boolean */
    public $autoDelete = false;

    /** @var boolean */
    public $nowait = false;

    /** @var array */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DECLARE);
    }

}
