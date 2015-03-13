<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionBlockedFrame extends MethodFrame
{

    /** @var string */
    public $reason = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_BLOCKED);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
