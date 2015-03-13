<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicGetFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $queue = '';

    /** @var boolean */
    public $noAck = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_GET);
    }

}
