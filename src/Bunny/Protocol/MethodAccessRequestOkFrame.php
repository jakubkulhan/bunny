<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodAccessRequestOkFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 1;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_ACCESS, Constants::METHOD_ACCESS_REQUEST_OK);
    }

}
