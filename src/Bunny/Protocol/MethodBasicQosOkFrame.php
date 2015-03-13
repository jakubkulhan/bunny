<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicQosOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_QOS_OK);
    }

}
