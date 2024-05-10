<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * AMQP 'connection.start-ok' (class #10, method #11) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodConnectionStartOkFrame extends MethodFrame
{

    /** @var string */
    public $response;

    /** @var array<mixed> */
    public $clientProperties = [];

    /** @var string */
    public $mechanism = 'PLAIN';

    /** @var string */
    public $locale = 'en_US';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_START_OK);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
