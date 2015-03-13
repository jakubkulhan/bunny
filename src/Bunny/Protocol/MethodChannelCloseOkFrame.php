<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodChannelCloseOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_CLOSE_OK);
    }

}
