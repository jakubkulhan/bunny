<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionSecureFrame extends MethodFrame
{

    /** @var string */
    public $challenge;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_SECURE);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
