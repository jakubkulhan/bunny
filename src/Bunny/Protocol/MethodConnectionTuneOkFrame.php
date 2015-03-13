<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionTuneOkFrame extends MethodFrame
{

    /** @var int */
    public $channelMax = 0;

    /** @var int */
    public $frameMax = 0;

    /** @var int */
    public $heartbeat = 0;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_TUNE_OK);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
