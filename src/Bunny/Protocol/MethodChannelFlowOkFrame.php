<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodChannelFlowOkFrame extends MethodFrame
{

    /** @var boolean */
    public $active;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_FLOW_OK);
    }

}
