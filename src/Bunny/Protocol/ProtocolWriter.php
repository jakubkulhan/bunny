<?php
namespace Bunny\Protocol;

use Bunny\Constants;
use Bunny\Exception\ProtocolException;

/**
 * AMQP protocol writer. This class provides means of transforming {@link AbstractFrame}s to their wire format.
 *
 * The class defines only most necessary methods, main logic of serializing frames is generated from spec
 * into trait {@link ProtocolWriterGenerated}.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ProtocolWriter
{

    use ProtocolWriterGenerated;

    /**
     * Appends AMQP frame to buffer.
     *
     * @param AbstractFrame $frame
     * @param Buffer $buffer
     */
    public function appendFrame(AbstractFrame $frame, Buffer $buffer)
    {
        if ($frame instanceof MethodFrame && $frame->payload !== null) {
            // payload already supplied

        } elseif ($frame instanceof MethodFrame) {
            $frameBuffer = new Buffer();
            $this->appendMethodFrame($frame, $frameBuffer);
            $frame->payloadSize = $frameBuffer->getLength();
            $frame->payload = $frameBuffer;

        } elseif ($frame instanceof ContentHeaderFrame) {
            $frameBuffer = new Buffer();
            // see https://github.com/pika/pika/blob/master/pika/spec.py class BasicProperties
            $frameBuffer->appendUint16($frame->classId);
            $frameBuffer->appendUint16($frame->weight);
            $frameBuffer->appendUint64($frame->bodySize);

            $flags = $frame->flags;

            $frameBuffer->appendUint16($flags);

            if ($flags & ContentHeaderFrame::FLAG_CONTENT_TYPE) {
                $frameBuffer->appendUint8(strlen($frame->contentType));
                $frameBuffer->append($frame->contentType);
            }

            if ($flags & ContentHeaderFrame::FLAG_CONTENT_ENCODING) {
                $frameBuffer->appendUint8(strlen($frame->contentEncoding));
                $frameBuffer->append($frame->contentEncoding);
            }

            if ($flags & ContentHeaderFrame::FLAG_HEADERS) {
                $this->appendTable($frame->headers, $frameBuffer);
            }

            if ($flags & ContentHeaderFrame::FLAG_DELIVERY_MODE) {
                $frameBuffer->appendUint8($frame->deliveryMode);
            }

            if ($flags & ContentHeaderFrame::FLAG_PRIORITY) {
                $frameBuffer->appendUint8($frame->priority);
            }

            if ($flags & ContentHeaderFrame::FLAG_CORRELATION_ID) {
                $frameBuffer->appendUint8(strlen($frame->correlationId));
                $frameBuffer->append($frame->correlationId);
            }

            if ($flags & ContentHeaderFrame::FLAG_REPLY_TO) {
                $frameBuffer->appendUint8(strlen($frame->replyTo));
                $frameBuffer->append($frame->replyTo);
            }

            if ($flags & ContentHeaderFrame::FLAG_EXPIRATION) {
                $frameBuffer->appendUint8(strlen($frame->expiration));
                $frameBuffer->append($frame->expiration);
            }

            if ($flags & ContentHeaderFrame::FLAG_MESSAGE_ID) {
                $frameBuffer->appendUint8(strlen($frame->messageId));
                $frameBuffer->append($frame->messageId);
            }

            if ($flags & ContentHeaderFrame::FLAG_TIMESTAMP) {
                $this->appendTimestamp($frame->timestamp, $frameBuffer);
            }

            if ($flags & ContentHeaderFrame::FLAG_TYPE) {
                $frameBuffer->appendUint8(strlen($frame->typeHeader));
                $frameBuffer->append($frame->typeHeader);
            }

            if ($flags & ContentHeaderFrame::FLAG_USER_ID) {
                $frameBuffer->appendUint8(strlen($frame->userId));
                $frameBuffer->append($frame->userId);
            }

            if ($flags & ContentHeaderFrame::FLAG_APP_ID) {
                $frameBuffer->appendUint8(strlen($frame->appId));
                $frameBuffer->append($frame->appId);
            }

            if ($flags & ContentHeaderFrame::FLAG_CLUSTER_ID) {
                $frameBuffer->appendUint8(strlen($frame->clusterId));
                $frameBuffer->append($frame->clusterId);
            }

            $frame->payloadSize = $frameBuffer->getLength();
            $frame->payload = $frameBuffer;

        } elseif ($frame instanceof ContentBodyFrame) {
            // body frame's payload is already loaded

        } elseif ($frame instanceof HeartbeatFrame) {
            // heartbeat frame is empty

        } else {
            throw new ProtocolException("Unhandled frame '" . get_class($frame) . "'.");
        }

        $buffer->appendUint8($frame->type);
        $buffer->appendUint16($frame->channel);
        $buffer->appendUint32($frame->payloadSize);
        $buffer->append($frame->payload);
        $buffer->appendUint8(Constants::FRAME_END);
    }

    /**
     * Appends AMQP table to buffer.
     *
     * @param array $table
     * @param Buffer $originalBuffer
     */
    public function appendTable(array $table, Buffer $originalBuffer)
    {
        $buffer = new Buffer();

        foreach ($table as $k => $v) {
            $buffer->appendUint8(strlen($k));
            $buffer->append($k);
            $this->appendFieldValue($v, $buffer);
        }

        $originalBuffer->appendUint32($buffer->getLength());
        $originalBuffer->append($buffer);
    }

    /**
     * Appends AMQP array to buffer.
     *
     * @param array $value
     * @param Buffer $originalBuffer
     */
    public function appendArray(array $value, Buffer $originalBuffer)
    {
        $buffer = new Buffer();

        foreach ($value as $v) {
            $this->appendFieldValue($v, $buffer);
        }

        $originalBuffer->appendUint32($buffer->getLength());
        $originalBuffer->append($buffer);
    }

    /**
     * Appends AMQP timestamp to buffer.
     *
     * @param \DateTime $value
     * @param Buffer $buffer
     */
    public function appendTimestamp(\DateTime $value, Buffer $buffer)
    {
        $buffer->appendUint64($value->getTimestamp());
    }

    /**
     * Appends packed bits to buffer.
     *
     * @param array $bits
     * @param Buffer $buffer
     */
    public function appendBits(array $bits, Buffer $buffer)
    {
        $value = 0;
        foreach ($bits as $n => $bit) {
            $bit = $bit ? 1 : 0;
            $value |= $bit << $n;
        }
        $buffer->appendUint8($value);
    }

    /**
     * Appends AMQP table/array field value to buffer.
     *
     * @param mixed $value
     * @param Buffer $buffer
     */
    public function appendFieldValue($value, Buffer $buffer)
    {
        if (is_string($value)) {
            $buffer->appendUint8(Constants::FIELD_LONG_STRING);
            $buffer->appendUint32(strlen($value));
            $buffer->append($value);

        } elseif (is_int($value)) {
            $buffer->appendUint8(Constants::FIELD_LONG_INT);
            $buffer->appendInt32($value);

        } elseif (is_bool($value)) {
            $buffer->appendUint8(Constants::FIELD_BOOLEAN);
            $buffer->appendUint8(intval($value));

        } elseif (is_float($value)) {
            $buffer->appendUint8(Constants::FIELD_DOUBLE);
            $buffer->appendDouble($value);

        } elseif (is_array($value)) {
            if (array_keys($value) === range(0, count($value) - 1)) { // sequential array
                $buffer->appendUint8(Constants::FIELD_ARRAY);
                $this->appendArray($value, $buffer);
            } else {
                $buffer->appendUint8(Constants::FIELD_TABLE);
                $this->appendTable($value, $buffer);
            }

        } elseif (is_null($value)) {
            $buffer->appendUint8(Constants::FIELD_NULL);

        } elseif ($value instanceof \DateTime) {
            $buffer->appendUint8(Constants::FIELD_TIMESTAMP);
            $this->appendTimestamp($value, $buffer);

        } else {
            throw new ProtocolException(
                "Unhandled value type '" . gettype($value) . "' " .
                (is_object($value) ? "(class " . get_class($value) . ")" : "") .
                "."
            );
        }
    }

}
