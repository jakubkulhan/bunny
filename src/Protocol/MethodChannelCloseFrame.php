<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'channel.close' (class #20, method #40) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodChannelCloseFrame extends MethodFrame
{

    /** @var int */
    public $replyCode;

    /** @var int */
    public $closeClassId;

    /** @var int */
    public $closeMethodId;

    /** @var string */
    public $replyText = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_CLOSE);
    }

}
