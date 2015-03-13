<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodChannelOpenOkFrame extends MethodFrame
{

    /** @var string */
    public $channelId = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_OPEN_OK);
    }

}
