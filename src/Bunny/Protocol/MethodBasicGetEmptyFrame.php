<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicGetEmptyFrame extends MethodFrame
{

    /** @var string */
    public $clusterId = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_GET_EMPTY);
    }

}
