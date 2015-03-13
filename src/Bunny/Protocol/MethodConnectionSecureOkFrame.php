<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodConnectionSecureOkFrame extends MethodFrame
{

    /** @var string */
    public $response;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_SECURE_OK);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
