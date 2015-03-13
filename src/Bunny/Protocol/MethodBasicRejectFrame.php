<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicRejectFrame extends MethodFrame
{

    /** @var int */
    public $deliveryTag;

    /** @var boolean */
    public $requeue = true;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_REJECT);
    }

}
