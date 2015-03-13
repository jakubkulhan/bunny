<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodQueueBindFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $queue = '';

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey = '';

    /** @var boolean */
    public $nowait = false;

    /** @var array */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_BIND);
    }

}
