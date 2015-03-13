<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodExchangeUnbindOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_UNBIND_OK);
    }

}
