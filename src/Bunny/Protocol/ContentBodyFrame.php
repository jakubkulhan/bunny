<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * Content body AMQP frame.
 *
 * Payload is opaque content being transferred. Size and number of body frames depends on preceding header frame
 * and it's body-size field.
 *
 *
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ContentBodyFrame extends AbstractFrame
{

    public function __construct($channel = null, $payloadSize = null, $payload = null)
    {
        parent::__construct(Constants::FRAME_BODY, $channel, $payloadSize, $payload);
    }

}
