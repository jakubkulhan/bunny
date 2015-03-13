<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicRecoverOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RECOVER_OK);
    }

}
