<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'basic.recover-ok' (class #60, method #111) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodBasicRecoverOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RECOVER_OK);
    }

}
