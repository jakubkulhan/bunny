<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.get-empty' (class #60, method #72) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicGetEmptyFrame extends MethodFrame
{

    /** @var string */
    public $clusterId = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_GET_EMPTY);
    }

}
