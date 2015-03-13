<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodQueueBindOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_BIND_OK);
    }

}
