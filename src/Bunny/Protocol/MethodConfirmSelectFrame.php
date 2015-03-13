<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConfirmSelectFrame extends MethodFrame
{

    /** @var boolean */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONFIRM, Constants::METHOD_CONFIRM_SELECT);
    }

}
