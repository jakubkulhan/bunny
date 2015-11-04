<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'channel.open' (class #20, method #10) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodChannelOpenFrame extends MethodFrame
{

    /** @var string */
    public $outOfBand = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_OPEN);
    }

}
