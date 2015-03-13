<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodTxSelectFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_TX, Constants::METHOD_TX_SELECT);
    }

}
