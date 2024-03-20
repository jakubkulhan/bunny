<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.open' (class #10, method #40) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionOpenFrame extends MethodFrame
{

    /** @var string */
    public $virtualHost = '/';

    /** @var string */
    public $capabilities = '';

    /** @var boolean */
    public $insist = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_OPEN);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
