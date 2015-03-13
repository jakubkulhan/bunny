<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionOpenOkFrame extends MethodFrame
{

    /** @var string */
    public $knownHosts = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_OPEN_OK);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
