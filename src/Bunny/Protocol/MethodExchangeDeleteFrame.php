<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodExchangeDeleteFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $exchange;

    /** @var boolean */
    public $ifUnused = false;

    /** @var boolean */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DELETE);
    }

}
