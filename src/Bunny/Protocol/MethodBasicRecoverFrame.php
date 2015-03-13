<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicRecoverFrame extends MethodFrame
{

    /** @var boolean */
    public $requeue = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RECOVER);
    }

}
