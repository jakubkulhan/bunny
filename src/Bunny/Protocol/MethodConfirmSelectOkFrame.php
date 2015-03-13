<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConfirmSelectOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONFIRM, Constants::METHOD_CONFIRM_SELECT_OK);
    }

}
