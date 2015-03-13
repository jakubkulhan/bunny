<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicCancelFrame extends MethodFrame
{

    /** @var string */
    public $consumerTag;

    /** @var boolean */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_CANCEL);
    }

}
