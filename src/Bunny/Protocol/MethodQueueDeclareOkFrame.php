<?php
namespace Bunny\Protocol;

use Bunny\Constants;

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
