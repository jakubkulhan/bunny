<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.secure' (class #10, method #20) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionSecureFrame extends MethodFrame
{

    /** @var string */
    public $challenge;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_SECURE);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
