<?php
namespace Bunny\Protocol;

use Bunny\Constants;

class MethodBasicConsumeFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 0;

    /** @var string */
    public $queue = '';

    /** @var string */
    public $consumerTag = '';

    /** @var boolean */
    public $noLocal = false;

    /** @var boolean */
    public $noAck = false;

    /** @var boolean */
    public $exclusive = false;

    /** @var boolean */
    public $nowait = false;

    /** @var array */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_CONSUME);
    }

}
