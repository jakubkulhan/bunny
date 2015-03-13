<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodQueueDeleteOkFrame extends MethodFrame
{

    /** @var int */
    public $messageCount;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DELETE_OK);
    }

}
