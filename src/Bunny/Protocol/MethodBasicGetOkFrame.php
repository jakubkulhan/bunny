<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicGetOkFrame extends MethodFrame
{

    /** @var int */
    public $deliveryTag;

    /** @var boolean */
    public $redelivered = false;

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey;

    /** @var int */
    public $messageCount;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_GET_OK);
    }

}
