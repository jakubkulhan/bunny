<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodQueueUnbindOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_UNBIND_OK);
    }

}
