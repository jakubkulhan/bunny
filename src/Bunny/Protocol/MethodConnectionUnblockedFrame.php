<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionUnblockedFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_UNBLOCKED);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
