<?php
namespace Bunny\Protocol;

use Bunny\Constants;
use Bunny\Exception\ProtocolException;

/**
 * AMQP protocol reader. This class provides means of transforming data from {@link Buffer} to {@link AbstractFrame}.
 *
 * The class defines only most necessary methods, main logic is of parsing methods frames is generated from spec
 * into trait {@link ProtocolReaderGenerated}.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ProtocolReader
{

    use ProtocolReaderGenerated;

    /**
     * Consumes AMQP frame from buffer.
     *
     * Returns NULL if there are not enough data to construct whole frame.
     *
     * @param Buffer $buffer
     * @return AbstractFrame
     */
    public function consumeFrame(Buffer $buffer)
    {
        // not enough data
        if ($buffer->getLength() < 7) {
            return null;
        }

        $type = $buffer->readUint8(0);
        $channel = $buffer->readUint16(1);
        $payloadSize = $buffer->readUint32(3);
        $payloadOffset = 7; // type:uint8=>1 + channel:uint16=>2 + payloadSize:uint32=>4 ==> 7

        // not enough data
        if ($buffer->getLength() < $payloadOffset + $payloadSize + 1 /* frame end byte */) {
            return null;
        }

        $buffer->consume(7);
        $payload = $buffer->consume($payloadSize);
        $frameEnd = $buffer->consumeUint8();

        if ($frameEnd !== Constants::FRAME_END) {
            throw new ProtocolException(sprintf("Frame end byte invalid - expected 0x%02x, got 0x%02x.", Constants::FRAME_END, $frameEnd));
        }

        $frameBuffer = new Buffer($payload);

        if ($type === Constants::FRAME_METHOD) {
            $frame = $this->consumeMethodFrame($frameBuffer);

        } elseif ($type === Constants::FRAME_HEADER) {
            // see https://github.com/pika/pika/blob/master/pika/spec.py class BasicProperties
            $frame = new ContentHeaderFrame();
            $frame->classId = $frameBuffer->consumeUint16();
            $frame->weight = $frameBuffer->consumeUint16();
            $frame->bodySize = $frameBuffer->consumeUint64();
            $frame->flags = $flags = $frameBuffer->consumeUint16();

            if ($flags & ContentHeaderFrame::FLAG_CONTENT_TYPE) {
                $frame->contentType = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_CONTENT_ENCODING) {
                $frame->contentEncoding = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_HEADERS) {
                $frame->headers = $this->consumeTable($frameBuffer);
            }

            if ($flags & ContentHeaderFrame::FLAG_DELIVERY_MODE) {
                $frame->deliveryMode = $frameBuffer->consumeUint8();
            }

            if ($flags & ContentHeaderFrame::FLAG_PRIORITY) {
                $frame->priority = $frameBuffer->consumeUint8();
            }

            if ($flags & ContentHeaderFrame::FLAG_CORRELATION_ID) {
                $frame->correlationId = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_REPLY_TO) {
                $frame->replyTo = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_EXPIRATION) {
                $frame->expiration = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_MESSAGE_ID) {
                $frame->messageId = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_TIMESTAMP) {
                $frame->timestamp = $this->consumeTimestamp($frameBuffer);
            }

            if ($flags & ContentHeaderFrame::FLAG_TYPE) {
                $frame->typeHeader = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_USER_ID) {
                $frame->userId = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_APP_ID) {
                $frame->appId = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

            if ($flags & ContentHeaderFrame::FLAG_CLUSTER_ID) {
                $frame->clusterId = $frameBuffer->consume($frameBuffer->consumeUint8());
            }

        } elseif ($type === Constants::FRAME_BODY) {
            $frame = new ContentBodyFrame();
            $frame->payload = $frameBuffer->consume($frameBuffer->getLength());

        } elseif ($type === Constants::FRAME_HEARTBEAT) {
            $frame = new HeartbeatFrame();
            if (!$frameBuffer->isEmpty()) {
                throw new ProtocolException("Heartbeat frame must be empty.");
            }

        } else {
            throw new ProtocolException("Unhandled frame type '{$type}'.");
        }

        if (!$frameBuffer->isEmpty()) {
            throw new ProtocolException("Frame buffer not entirely consumed.");
        }

        /** @var AbstractFrame $frame */
        $frame->type = $type;
        $frame->channel = $channel;
        $frame->payloadSize = $payloadSize;
        // DO NOT CALL! ContentBodyFrame uses payload for body
        // $frame->setPayload($payload);

        return $frame;
    }

    /**
     * Consumes AMQP table from buffer.
     *
     * @param Buffer $originalBuffer
     * @return array
     */
    public function consumeTable(Buffer $originalBuffer)
    {
        $buffer = $originalBuffer->consumeSlice($originalBuffer->consumeUint32());

        $data = [];
        while (!$buffer->isEmpty()) {
            $data[$buffer->consume($buffer->consumeUint8())] = $this->consumeFieldValue($buffer);
        }

        return $data;
    }

    /**
     * Consumes AMQP array from buffer.
     *
     * @param Buffer $originalBuffer
     * @return array
     */
    public function consumeArray(Buffer $originalBuffer)
    {
        $buffer = $originalBuffer->consumeSlice($originalBuffer->consumeUint32());
        $data = [];
        while (!$buffer->isEmpty()) {
            $data[] = $this->consumeFieldValue($buffer);
        }
        return $data;
    }

    /**
     * Consumes AMQP timestamp from buffer.
     *
     * @param Buffer $buffer
     * @return \DateTime
     */
    public function consumeTimestamp(Buffer $buffer)
    {
        $d = new \DateTime();
        $d->setTimestamp($buffer->consumeUint64());
        return $d;
    }

    /**
     * Consumes packed bits from buffer.
     *
     * @param Buffer $buffer
     * @param int $n
     * @return array
     */
    public function consumeBits(Buffer $buffer, $n)
    {
        $bits = [];
        $value = $buffer->consumeUint8();
        for ($i = 0; $i < $n; ++$i) {
            $bits[] = ($value & (1 << $i)) > 0;
        }
        return $bits;
    }

    /**
     * Consumes AMQP decimal value.
     *
     * @param Buffer $buffer
     * @return int
     */
    public function consumeDecimalValue(Buffer $buffer)
    {
        $scale = $buffer->consumeUint8();
        $value = $buffer->consumeUint32();
        return $value * pow(10, $scale);
    }

    /**
     * Consumes AMQP table/array field value.
     *
     * @param Buffer $buffer
     * @return mixed
     */
    public function consumeFieldValue(Buffer $buffer)
    {
        $fieldType = $buffer->consumeUint8();

        switch ($fieldType) {
            case Constants::FIELD_BOOLEAN:
                return $buffer->consumeUint8() > 0;
            case Constants::FIELD_SHORT_SHORT_INT:
                return $buffer->consumeInt8();
            case Constants::FIELD_SHORT_SHORT_UINT:
                return $buffer->consumeUint8();
            case Constants::FIELD_SHORT_INT:
                return $buffer->consumeInt16();
            case Constants::FIELD_SHORT_UINT:
                return $buffer->consumeUint16();
            case Constants::FIELD_LONG_INT:
                return $buffer->consumeInt32();
            case Constants::FIELD_LONG_UINT:
                return $buffer->consumeUint32();
            case Constants::FIELD_LONG_LONG_INT:
                return $buffer->consumeInt64();
            case Constants::FIELD_LONG_LONG_UINT:
                return $buffer->consumeUint64();
            case Constants::FIELD_FLOAT:
                return $buffer->consumeFloat();
            case Constants::FIELD_DOUBLE:
                return $buffer->consumeDouble();
            case Constants::FIELD_DECIMAL_VALUE:
                return $this->consumeDecimalValue($buffer);
            case Constants::FIELD_SHORT_STRING:
                return $buffer->consume($buffer->consumeUint8());
            case Constants::FIELD_LONG_STRING:
                return $buffer->consume($buffer->consumeUint32());
            case Constants::FIELD_ARRAY:
                return $this->consumeArray($buffer);
            case Constants::FIELD_TIMESTAMP:
                return $this->consumeTimestamp($buffer);
            case Constants::FIELD_TABLE:
                return $this->consumeTable($buffer);
            case Constants::FIELD_NULL:
                return null;

            default:
                throw new ProtocolException(
                    sprintf("Unhandled field type 0x%02x", $fieldType) .
                    (ctype_print(chr($fieldType)) ? " ('" . chr($fieldType) . "')" : "") .
                    "."
                );
        }
    }

}
