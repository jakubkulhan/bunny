<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.start' (class #10, method #10) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionStartFrame extends MethodFrame
{

    /** @var int */
    public $versionMajor = 0;

    /** @var int */
    public $versionMinor = 9;

    /** @var array */
    public $serverProperties = [];

    /** @var string */
    public $mechanisms = 'PLAIN';

    /** @var string */
    public $locales = 'en_US';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_START);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
