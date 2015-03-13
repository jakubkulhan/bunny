<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicAckFrame extends MethodFrame
{

    /** @var int */
    public $deliveryTag = 0;

    /** @var boolean */
    public $multiple = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_ACK);
    }

}
