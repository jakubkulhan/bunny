<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.secure-ok' (class #10, method #21) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
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
