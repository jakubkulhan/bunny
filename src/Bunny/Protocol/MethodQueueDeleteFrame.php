<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodQueueDeleteFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $queue = '';

    /** @var boolean */
    public $ifUnused = false;

    /** @var boolean */
    public $ifEmpty = false;

    /** @var boolean */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DELETE);
    }

}
