<?php
namespace Bunny\Protocol;

/**
 * Base class for all AMQP protocol frames.
 *
 * Frame classes' sole purpose is to be crate for transferring data. All fields are public because of calls to getters
 * and setters are ridiculously slow.
 *
 * You should not mangle with frame's fields, everything should be handled by classes in {@namespace \Bunny\Protocol}.
 *
 * Frame's wire format:
 *
 *     0      1         3              7               size+7     size+8
 *     +------+---------+--------------+-----------------+-----------+
 *     | type | channel |     size     | ... payload ... | frame-end |
 *     +------+---------+--------------+-----------------+-----------+
 *      uint8    uint16      uint32        size octets       uint8
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
abstract class AbstractFrame
{

    /** @var int */
    public $type;

    /** @var int */
    public $channel;

    /** @var int */
    public $payloadSize;

    /** @var string */
    public $payload;

    public static function create()
    {
        return new static();
    }

    public function __construct($type = null, $channel = null, $payloadSize = null, $payload = null)
    {
        $this->type = $type;
        $this->channel = $channel;
        $this->payloadSize = $payloadSize;
        $this->payload = $payload;
    }

}
