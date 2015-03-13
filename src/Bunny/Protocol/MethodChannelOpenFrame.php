<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodChannelOpenFrame extends MethodFrame
{

    /** @var string */
    public $outOfBand = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_OPEN);
    }

}
