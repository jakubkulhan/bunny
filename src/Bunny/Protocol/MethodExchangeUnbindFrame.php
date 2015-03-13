<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodExchangeUnbindFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $destination;

    /** @var string */
    public $source;

    /** @var string */
    public $routingKey = '';

    /** @var boolean */
    public $nowait = false;

    /** @var array */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_UNBIND);
    }

}
