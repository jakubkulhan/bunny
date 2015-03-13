<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionCloseOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_CLOSE_OK);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
