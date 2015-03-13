<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionCloseFrame extends MethodFrame
{

    /** @var int */
    public $replyCode;

    /** @var string */
    public $replyText = '';

    /** @var int */
    public $closeClassId;

    /** @var int */
    public $closeMethodId;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_CLOSE);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
