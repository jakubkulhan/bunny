<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodExchangeDeclareOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DECLARE_OK);
    }

}
