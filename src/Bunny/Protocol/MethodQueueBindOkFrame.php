<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'queue.bind-ok' (class #50, method #21) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodQueueBindOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_BIND_OK);
    }

}
