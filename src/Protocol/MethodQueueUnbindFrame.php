<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'queue.unbind' (class #50, method #50) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodQueueUnbindFrame extends MethodFrame
{

    /** @var string */
    public $exchange;

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $queue = '';

    /** @var string */
    public $routingKey = '';

    /** @var array<mixed> */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_UNBIND);
    }

}
