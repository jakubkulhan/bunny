<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'access.request-ok' (class #30, method #11) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodAccessRequestOkFrame extends MethodFrame
{

    /** @var int */
    public $reserved1 = 1;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_ACCESS, Constants::METHOD_ACCESS_REQUEST_OK);
    }

}
