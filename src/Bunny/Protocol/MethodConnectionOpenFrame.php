<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionOpenFrame extends MethodFrame
{

    /** @var string */
    public $virtualHost = '/';

    /** @var string */
    public $capabilities = '';

    /** @var boolean */
    public $insist = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_OPEN);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
