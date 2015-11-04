<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.open-ok' (class #10, method #41) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionOpenOkFrame extends MethodFrame
{

    /** @var string */
    public $knownHosts = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_OPEN_OK);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
