<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'queue.bind' (class #50, method #20) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodQueueBindFrame extends MethodFrame
{

    /** @var string */
    public $exchange;

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $queue = '';

    /** @var string */
    public $routingKey = '';

    /** @var bool */
    public $nowait = false;

    /** @var array<mixed> */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_BIND);
    }

}
