<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicReturnFrame extends MethodFrame
{

    /** @var int */
    public $replyCode;

    /** @var string */
    public $replyText = '';

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RETURN);
    }

}
