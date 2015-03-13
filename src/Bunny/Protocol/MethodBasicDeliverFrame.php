<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicDeliverFrame extends MethodFrame
{

    /** @var string */
    public $consumerTag;

    /** @var int */
    public $deliveryTag;

    /** @var boolean */
    public $redelivered = false;

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_DELIVER);
    }

}
