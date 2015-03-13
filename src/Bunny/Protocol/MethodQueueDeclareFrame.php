<?php
namespace Bunny\Protocol;

use Bunny\Constants;

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
