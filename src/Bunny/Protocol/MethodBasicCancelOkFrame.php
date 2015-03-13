<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicCancelOkFrame extends MethodFrame
{

    /** @var string */
    public $consumerTag;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_CANCEL_OK);
    }

}
