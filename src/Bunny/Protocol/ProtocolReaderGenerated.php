<?php
namespace Bunny\Protocol;

use Bunny\Constants;
use Bunny\Exception\InvalidClassException;
use Bunny\Exception\InvalidMethodException;

/**
 * AMQP-0-9-1 protocol reader
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
trait ProtocolReaderGenerated
{

    /**
     * Consumes AMQP table from buffer.
     * 
     * @param Buffer $originalBuffer
     * @return array
     */
    abstract public function consumeTable(Buffer $originalBuffer);

    /**
     * Consumes packed bits from buffer.
     *
     * @param Buffer $buffer
     * @param int $n
     * @return array
     */
    abstract public function consumeBits(Buffer $buffer, $n);

    /**
     * Consumes AMQP method frame.
     *
     * @param Buffer $buffer
     * @return MethodFrame
     */
    public function consumeMethodFrame(Buffer $buffer)
    {
        $classId = $buffer->consumeUint16();
        $methodId = $buffer->consumeUint16();

        if ($classId === Constants::CLASS_CONNECTION) {
            if ($methodId === Constants::METHOD_CONNECTION_START) {
                $frame = new MethodConnectionStartFrame();
                $frame->versionMajor = $buffer->consumeUint8();
                $frame->versionMinor = $buffer->consumeUint8();
                $frame->serverProperties = $this->consumeTable($buffer);
                $frame->mechanisms = $buffer->consume($buffer->consumeUint32());
                $frame->locales = $buffer->consume($buffer->consumeUint32());
            } elseif ($methodId === Constants::METHOD_CONNECTION_START_OK) {
                $frame = new MethodConnectionStartOkFrame();
                $frame->clientProperties = $this->consumeTable($buffer);
                $frame->mechanism = $buffer->consume($buffer->consumeUint8());
                $frame->response = $buffer->consume($buffer->consumeUint32());
                $frame->locale = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_CONNECTION_SECURE) {
                $frame = new MethodConnectionSecureFrame();
                $frame->challenge = $buffer->consume($buffer->consumeUint32());
            } elseif ($methodId === Constants::METHOD_CONNECTION_SECURE_OK) {
                $frame = new MethodConnectionSecureOkFrame();
                $frame->response = $buffer->consume($buffer->consumeUint32());
            } elseif ($methodId === Constants::METHOD_CONNECTION_TUNE) {
                $frame = new MethodConnectionTuneFrame();
                $frame->channelMax = $buffer->consumeInt16();
                $frame->frameMax = $buffer->consumeInt32();
                $frame->heartbeat = $buffer->consumeInt16();
            } elseif ($methodId === Constants::METHOD_CONNECTION_TUNE_OK) {
                $frame = new MethodConnectionTuneOkFrame();
                $frame->channelMax = $buffer->consumeInt16();
                $frame->frameMax = $buffer->consumeInt32();
                $frame->heartbeat = $buffer->consumeInt16();
            } elseif ($methodId === Constants::METHOD_CONNECTION_OPEN) {
                $frame = new MethodConnectionOpenFrame();
                $frame->virtualHost = $buffer->consume($buffer->consumeUint8());
                $frame->capabilities = $buffer->consume($buffer->consumeUint8());
                list($frame->insist) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_CONNECTION_OPEN_OK) {
                $frame = new MethodConnectionOpenOkFrame();
                $frame->knownHosts = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_CONNECTION_CLOSE) {
                $frame = new MethodConnectionCloseFrame();
                $frame->replyCode = $buffer->consumeInt16();
                $frame->replyText = $buffer->consume($buffer->consumeUint8());
                $frame->closeClassId = $buffer->consumeInt16();
                $frame->closeMethodId = $buffer->consumeInt16();
            } elseif ($methodId === Constants::METHOD_CONNECTION_CLOSE_OK) {
                $frame = new MethodConnectionCloseOkFrame();
            } elseif ($methodId === Constants::METHOD_CONNECTION_BLOCKED) {
                $frame = new MethodConnectionBlockedFrame();
                $frame->reason = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_CONNECTION_UNBLOCKED) {
                $frame = new MethodConnectionUnblockedFrame();
            } else {
                throw new InvalidMethodException($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_CHANNEL) {
            if ($methodId === Constants::METHOD_CHANNEL_OPEN) {
                $frame = new MethodChannelOpenFrame();
                $frame->outOfBand = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_CHANNEL_OPEN_OK) {
                $frame = new MethodChannelOpenOkFrame();
                $frame->channelId = $buffer->consume($buffer->consumeUint32());
            } elseif ($methodId === Constants::METHOD_CHANNEL_FLOW) {
                $frame = new MethodChannelFlowFrame();
                list($frame->active) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_CHANNEL_FLOW_OK) {
                $frame = new MethodChannelFlowOkFrame();
                list($frame->active) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_CHANNEL_CLOSE) {
                $frame = new MethodChannelCloseFrame();
                $frame->replyCode = $buffer->consumeInt16();
                $frame->replyText = $buffer->consume($buffer->consumeUint8());
                $frame->closeClassId = $buffer->consumeInt16();
                $frame->closeMethodId = $buffer->consumeInt16();
            } elseif ($methodId === Constants::METHOD_CHANNEL_CLOSE_OK) {
                $frame = new MethodChannelCloseOkFrame();
            } else {
                throw new InvalidMethodException($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_ACCESS) {
            if ($methodId === Constants::METHOD_ACCESS_REQUEST) {
                $frame = new MethodAccessRequestFrame();
                $frame->realm = $buffer->consume($buffer->consumeUint8());
                list($frame->exclusive, $frame->passive, $frame->active, $frame->write, $frame->read) = $this->consumeBits($buffer, 5);
            } elseif ($methodId === Constants::METHOD_ACCESS_REQUEST_OK) {
                $frame = new MethodAccessRequestOkFrame();
                $frame->reserved1 = $buffer->consumeInt16();
            } else {
                throw new InvalidMethodException($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_EXCHANGE) {
            if ($methodId === Constants::METHOD_EXCHANGE_DECLARE) {
                $frame = new MethodExchangeDeclareFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->exchange = $buffer->consume($buffer->consumeUint8());
                $frame->exchangeType = $buffer->consume($buffer->consumeUint8());
                list($frame->passive, $frame->durable, $frame->autoDelete, $frame->internal, $frame->nowait) = $this->consumeBits($buffer, 5);
                $frame->arguments = $this->consumeTable($buffer);
            } elseif ($methodId === Constants::METHOD_EXCHANGE_DECLARE_OK) {
                $frame = new MethodExchangeDeclareOkFrame();
            } elseif ($methodId === Constants::METHOD_EXCHANGE_DELETE) {
                $frame = new MethodExchangeDeleteFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->exchange = $buffer->consume($buffer->consumeUint8());
                list($frame->ifUnused, $frame->nowait) = $this->consumeBits($buffer, 2);
            } elseif ($methodId === Constants::METHOD_EXCHANGE_DELETE_OK) {
                $frame = new MethodExchangeDeleteOkFrame();
            } elseif ($methodId === Constants::METHOD_EXCHANGE_BIND) {
                $frame = new MethodExchangeBindFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->destination = $buffer->consume($buffer->consumeUint8());
                $frame->source = $buffer->consume($buffer->consumeUint8());
                $frame->routingKey = $buffer->consume($buffer->consumeUint8());
                list($frame->nowait) = $this->consumeBits($buffer, 1);
                $frame->arguments = $this->consumeTable($buffer);
            } elseif ($methodId === Constants::METHOD_EXCHANGE_BIND_OK) {
                $frame = new MethodExchangeBindOkFrame();
            } elseif ($methodId === Constants::METHOD_EXCHANGE_UNBIND) {
                $frame = new MethodExchangeUnbindFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->destination = $buffer->consume($buffer->consumeUint8());
                $frame->source = $buffer->consume($buffer->consumeUint8());
                $frame->routingKey = $buffer->consume($buffer->consumeUint8());
                list($frame->nowait) = $this->consumeBits($buffer, 1);
                $frame->arguments = $this->consumeTable($buffer);
            } elseif ($methodId === Constants::METHOD_EXCHANGE_UNBIND_OK) {
                $frame = new MethodExchangeUnbindOkFrame();
            } else {
                throw new InvalidMethodException($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_QUEUE) {
            if ($methodId === Constants::METHOD_QUEUE_DECLARE) {
                $frame = new MethodQueueDeclareFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consume($buffer->consumeUint8());
                list($frame->passive, $frame->durable, $frame->exclusive, $frame->autoDelete, $frame->nowait) = $this->consumeBits($buffer, 5);
                $frame->arguments = $this->consumeTable($buffer);
            } elseif ($methodId === Constants::METHOD_QUEUE_DECLARE_OK) {
                $frame = new MethodQueueDeclareOkFrame();
                $frame->queue = $buffer->consume($buffer->consumeUint8());
                $frame->messageCount = $buffer->consumeInt32();
                $frame->consumerCount = $buffer->consumeInt32();
            } elseif ($methodId === Constants::METHOD_QUEUE_BIND) {
                $frame = new MethodQueueBindFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consume($buffer->consumeUint8());
                $frame->exchange = $buffer->consume($buffer->consumeUint8());
                $frame->routingKey = $buffer->consume($buffer->consumeUint8());
                list($frame->nowait) = $this->consumeBits($buffer, 1);
                $frame->arguments = $this->consumeTable($buffer);
            } elseif ($methodId === Constants::METHOD_QUEUE_BIND_OK) {
                $frame = new MethodQueueBindOkFrame();
            } elseif ($methodId === Constants::METHOD_QUEUE_PURGE) {
                $frame = new MethodQueuePurgeFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consume($buffer->consumeUint8());
                list($frame->nowait) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_QUEUE_PURGE_OK) {
                $frame = new MethodQueuePurgeOkFrame();
                $frame->messageCount = $buffer->consumeInt32();
            } elseif ($methodId === Constants::METHOD_QUEUE_DELETE) {
                $frame = new MethodQueueDeleteFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consume($buffer->consumeUint8());
                list($frame->ifUnused, $frame->ifEmpty, $frame->nowait) = $this->consumeBits($buffer, 3);
            } elseif ($methodId === Constants::METHOD_QUEUE_DELETE_OK) {
                $frame = new MethodQueueDeleteOkFrame();
                $frame->messageCount = $buffer->consumeInt32();
            } elseif ($methodId === Constants::METHOD_QUEUE_UNBIND) {
                $frame = new MethodQueueUnbindFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consume($buffer->consumeUint8());
                $frame->exchange = $buffer->consume($buffer->consumeUint8());
                $frame->routingKey = $buffer->consume($buffer->consumeUint8());
                $frame->arguments = $this->consumeTable($buffer);
            } elseif ($methodId === Constants::METHOD_QUEUE_UNBIND_OK) {
                $frame = new MethodQueueUnbindOkFrame();
            } else {
                throw new InvalidMethodException($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_BASIC) {
            if ($methodId === Constants::METHOD_BASIC_QOS) {
                $frame = new MethodBasicQosFrame();
                $frame->prefetchSize = $buffer->consumeInt32();
                $frame->prefetchCount = $buffer->consumeInt16();
                list($frame->global) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_BASIC_QOS_OK) {
                $frame = new MethodBasicQosOkFrame();
            } elseif ($methodId === Constants::METHOD_BASIC_CONSUME) {
                $frame = new MethodBasicConsumeFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consume($buffer->consumeUint8());
                $frame->consumerTag = $buffer->consume($buffer->consumeUint8());
                list($frame->noLocal, $frame->noAck, $frame->exclusive, $frame->nowait) = $this->consumeBits($buffer, 4);
                $frame->arguments = $this->consumeTable($buffer);
            } elseif ($methodId === Constants::METHOD_BASIC_CONSUME_OK) {
                $frame = new MethodBasicConsumeOkFrame();
                $frame->consumerTag = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_BASIC_CANCEL) {
                $frame = new MethodBasicCancelFrame();
                $frame->consumerTag = $buffer->consume($buffer->consumeUint8());
                list($frame->nowait) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_BASIC_CANCEL_OK) {
                $frame = new MethodBasicCancelOkFrame();
                $frame->consumerTag = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_BASIC_PUBLISH) {
                $frame = new MethodBasicPublishFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->exchange = $buffer->consume($buffer->consumeUint8());
                $frame->routingKey = $buffer->consume($buffer->consumeUint8());
                list($frame->mandatory, $frame->immediate) = $this->consumeBits($buffer, 2);
            } elseif ($methodId === Constants::METHOD_BASIC_RETURN) {
                $frame = new MethodBasicReturnFrame();
                $frame->replyCode = $buffer->consumeInt16();
                $frame->replyText = $buffer->consume($buffer->consumeUint8());
                $frame->exchange = $buffer->consume($buffer->consumeUint8());
                $frame->routingKey = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_BASIC_DELIVER) {
                $frame = new MethodBasicDeliverFrame();
                $frame->consumerTag = $buffer->consume($buffer->consumeUint8());
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->redelivered) = $this->consumeBits($buffer, 1);
                $frame->exchange = $buffer->consume($buffer->consumeUint8());
                $frame->routingKey = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_BASIC_GET) {
                $frame = new MethodBasicGetFrame();
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consume($buffer->consumeUint8());
                list($frame->noAck) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_BASIC_GET_OK) {
                $frame = new MethodBasicGetOkFrame();
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->redelivered) = $this->consumeBits($buffer, 1);
                $frame->exchange = $buffer->consume($buffer->consumeUint8());
                $frame->routingKey = $buffer->consume($buffer->consumeUint8());
                $frame->messageCount = $buffer->consumeInt32();
            } elseif ($methodId === Constants::METHOD_BASIC_GET_EMPTY) {
                $frame = new MethodBasicGetEmptyFrame();
                $frame->clusterId = $buffer->consume($buffer->consumeUint8());
            } elseif ($methodId === Constants::METHOD_BASIC_ACK) {
                $frame = new MethodBasicAckFrame();
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->multiple) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_BASIC_REJECT) {
                $frame = new MethodBasicRejectFrame();
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->requeue) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_BASIC_RECOVER_ASYNC) {
                $frame = new MethodBasicRecoverAsyncFrame();
                list($frame->requeue) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_BASIC_RECOVER) {
                $frame = new MethodBasicRecoverFrame();
                list($frame->requeue) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_BASIC_RECOVER_OK) {
                $frame = new MethodBasicRecoverOkFrame();
            } elseif ($methodId === Constants::METHOD_BASIC_NACK) {
                $frame = new MethodBasicNackFrame();
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->multiple, $frame->requeue) = $this->consumeBits($buffer, 2);
            } else {
                throw new InvalidMethodException($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_TX) {
            if ($methodId === Constants::METHOD_TX_SELECT) {
                $frame = new MethodTxSelectFrame();
            } elseif ($methodId === Constants::METHOD_TX_SELECT_OK) {
                $frame = new MethodTxSelectOkFrame();
            } elseif ($methodId === Constants::METHOD_TX_COMMIT) {
                $frame = new MethodTxCommitFrame();
            } elseif ($methodId === Constants::METHOD_TX_COMMIT_OK) {
                $frame = new MethodTxCommitOkFrame();
            } elseif ($methodId === Constants::METHOD_TX_ROLLBACK) {
                $frame = new MethodTxRollbackFrame();
            } elseif ($methodId === Constants::METHOD_TX_ROLLBACK_OK) {
                $frame = new MethodTxRollbackOkFrame();
            } else {
                throw new InvalidMethodException($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_CONFIRM) {
            if ($methodId === Constants::METHOD_CONFIRM_SELECT) {
                $frame = new MethodConfirmSelectFrame();
                list($frame->nowait) = $this->consumeBits($buffer, 1);
            } elseif ($methodId === Constants::METHOD_CONFIRM_SELECT_OK) {
                $frame = new MethodConfirmSelectOkFrame();
            } else {
                throw new InvalidMethodException($classId, $methodId);
            }
        } else {
            throw new InvalidClassException($classId);
        }

        $frame->classId = $classId;
        $frame->methodId = $methodId;

        return $frame;
    }

}
