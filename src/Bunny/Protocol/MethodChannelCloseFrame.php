<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodChannelCloseFrame extends MethodFrame
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
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_CLOSE);
    }

}
