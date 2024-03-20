<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'queue.delete-ok' (class #50, method #41) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodQueueDeleteOkFrame extends MethodFrame
{

    /** @var int */
    public $messageCount;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DELETE_OK);
    }

}
