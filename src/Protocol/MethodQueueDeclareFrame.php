<?php

declare(strict_types=1);

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

    /** @var bool */
    public $passive = false;

    /** @var bool */
    public $durable = false;

    /** @var bool */
    public $exclusive = false;

    /** @var bool */
    public $autoDelete = false;

    /** @var bool */
    public $nowait = false;

    /** @var array<mixed> */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DECLARE);
    }

}
