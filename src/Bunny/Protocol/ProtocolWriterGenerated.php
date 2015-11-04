<?php
namespace Bunny\Protocol;

use Bunny\Exception\ProtocolException;


/**
 * AMQP-0-9-1 protocol writer
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
trait ProtocolWriterGenerated
{

    /**
     * Appends AMQP table to buffer.
     *
     * @param array $table
     * @param Buffer $originalBuffer
     */
    abstract public function appendTable(array $table, Buffer $originalBuffer);

    /**
     * Appends packed bits to buffer.
     *
     * @param array $bits
     * @param Buffer $buffer
     */
    abstract public function appendBits(array $bits, Buffer $buffer);

    /**
     * Appends AMQP protocol header to buffer.
     *
     * @param Buffer $buffer
     */
    public function appendProtocolHeader(Buffer $buffer)
    {
        $buffer->append('AMQP');
        $buffer->appendUint8(0);
        $buffer->appendUint8(0);
        $buffer->appendUint8(9);
        $buffer->appendUint8(1);
    }

    /**
     * Appends AMQP method frame to buffer.
     *
     * @param MethodFrame $frame
     * @param Buffer $buffer
     */
    public function appendMethodFrame(MethodFrame $frame, Buffer $buffer)
    {
        $buffer->appendUint16($frame->classId);
        $buffer->appendUint16($frame->methodId);

        if ($frame instanceof MethodConnectionStartFrame) {
            $buffer->appendUint8($frame->versionMajor);
            $buffer->appendUint8($frame->versionMinor);
            $this->appendTable($frame->serverProperties, $buffer);
            $buffer->appendUint32(strlen($frame->mechanisms)); $buffer->append($frame->mechanisms);
            $buffer->appendUint32(strlen($frame->locales)); $buffer->append($frame->locales);
        } elseif ($frame instanceof MethodConnectionStartOkFrame) {
            $this->appendTable($frame->clientProperties, $buffer);
            $buffer->appendUint8(strlen($frame->mechanism)); $buffer->append($frame->mechanism);
            $buffer->appendUint32(strlen($frame->response)); $buffer->append($frame->response);
            $buffer->appendUint8(strlen($frame->locale)); $buffer->append($frame->locale);
        } elseif ($frame instanceof MethodConnectionSecureFrame) {
            $buffer->appendUint32(strlen($frame->challenge)); $buffer->append($frame->challenge);
        } elseif ($frame instanceof MethodConnectionSecureOkFrame) {
            $buffer->appendUint32(strlen($frame->response)); $buffer->append($frame->response);
        } elseif ($frame instanceof MethodConnectionTuneFrame) {
            $buffer->appendInt16($frame->channelMax);
            $buffer->appendInt32($frame->frameMax);
            $buffer->appendInt16($frame->heartbeat);
        } elseif ($frame instanceof MethodConnectionTuneOkFrame) {
            $buffer->appendInt16($frame->channelMax);
            $buffer->appendInt32($frame->frameMax);
            $buffer->appendInt16($frame->heartbeat);
        } elseif ($frame instanceof MethodConnectionOpenFrame) {
            $buffer->appendUint8(strlen($frame->virtualHost)); $buffer->append($frame->virtualHost);
            $buffer->appendUint8(strlen($frame->capabilities)); $buffer->append($frame->capabilities);
            $this->appendBits([$frame->insist], $buffer);
        } elseif ($frame instanceof MethodConnectionOpenOkFrame) {
            $buffer->appendUint8(strlen($frame->knownHosts)); $buffer->append($frame->knownHosts);
        } elseif ($frame instanceof MethodConnectionCloseFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendUint8(strlen($frame->replyText)); $buffer->append($frame->replyText);
            $buffer->appendInt16($frame->closeClassId);
            $buffer->appendInt16($frame->closeMethodId);
        } elseif ($frame instanceof MethodConnectionCloseOkFrame) {
        } elseif ($frame instanceof MethodConnectionBlockedFrame) {
            $buffer->appendUint8(strlen($frame->reason)); $buffer->append($frame->reason);
        } elseif ($frame instanceof MethodConnectionUnblockedFrame) {
        } elseif ($frame instanceof MethodChannelOpenFrame) {
            $buffer->appendUint8(strlen($frame->outOfBand)); $buffer->append($frame->outOfBand);
        } elseif ($frame instanceof MethodChannelOpenOkFrame) {
            $buffer->appendUint32(strlen($frame->channelId)); $buffer->append($frame->channelId);
        } elseif ($frame instanceof MethodChannelFlowFrame) {
            $this->appendBits([$frame->active], $buffer);
        } elseif ($frame instanceof MethodChannelFlowOkFrame) {
            $this->appendBits([$frame->active], $buffer);
        } elseif ($frame instanceof MethodChannelCloseFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendUint8(strlen($frame->replyText)); $buffer->append($frame->replyText);
            $buffer->appendInt16($frame->closeClassId);
            $buffer->appendInt16($frame->closeMethodId);
        } elseif ($frame instanceof MethodChannelCloseOkFrame) {
        } elseif ($frame instanceof MethodAccessRequestFrame) {
            $buffer->appendUint8(strlen($frame->realm)); $buffer->append($frame->realm);
            $this->appendBits([$frame->exclusive, $frame->passive, $frame->active, $frame->write, $frame->read], $buffer);
        } elseif ($frame instanceof MethodAccessRequestOkFrame) {
            $buffer->appendInt16($frame->reserved1);
        } elseif ($frame instanceof MethodExchangeDeclareFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(strlen($frame->exchangeType)); $buffer->append($frame->exchangeType);
            $this->appendBits([$frame->passive, $frame->durable, $frame->autoDelete, $frame->internal, $frame->nowait], $buffer);
            $this->appendTable($frame->arguments, $buffer);
        } elseif ($frame instanceof MethodExchangeDeclareOkFrame) {
        } elseif ($frame instanceof MethodExchangeDeleteFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->exchange)); $buffer->append($frame->exchange);
            $this->appendBits([$frame->ifUnused, $frame->nowait], $buffer);
        } elseif ($frame instanceof MethodExchangeDeleteOkFrame) {
        } elseif ($frame instanceof MethodExchangeBindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->destination)); $buffer->append($frame->destination);
            $buffer->appendUint8(strlen($frame->source)); $buffer->append($frame->source);
            $buffer->appendUint8(strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $this->appendBits([$frame->nowait], $buffer);
            $this->appendTable($frame->arguments, $buffer);
        } elseif ($frame instanceof MethodExchangeBindOkFrame) {
        } elseif ($frame instanceof MethodExchangeUnbindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->destination)); $buffer->append($frame->destination);
            $buffer->appendUint8(strlen($frame->source)); $buffer->append($frame->source);
            $buffer->appendUint8(strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $this->appendBits([$frame->nowait], $buffer);
            $this->appendTable($frame->arguments, $buffer);
        } elseif ($frame instanceof MethodExchangeUnbindOkFrame) {
        } elseif ($frame instanceof MethodQueueDeclareFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->queue)); $buffer->append($frame->queue);
            $this->appendBits([$frame->passive, $frame->durable, $frame->exclusive, $frame->autoDelete, $frame->nowait], $buffer);
            $this->appendTable($frame->arguments, $buffer);
        } elseif ($frame instanceof MethodQueueDeclareOkFrame) {
            $buffer->appendUint8(strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendInt32($frame->messageCount);
            $buffer->appendInt32($frame->consumerCount);
        } elseif ($frame instanceof MethodQueueBindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendUint8(strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $this->appendBits([$frame->nowait], $buffer);
            $this->appendTable($frame->arguments, $buffer);
        } elseif ($frame instanceof MethodQueueBindOkFrame) {
        } elseif ($frame instanceof MethodQueuePurgeFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->queue)); $buffer->append($frame->queue);
            $this->appendBits([$frame->nowait], $buffer);
        } elseif ($frame instanceof MethodQueuePurgeOkFrame) {
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof MethodQueueDeleteFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->queue)); $buffer->append($frame->queue);
            $this->appendBits([$frame->ifUnused, $frame->ifEmpty, $frame->nowait], $buffer);
        } elseif ($frame instanceof MethodQueueDeleteOkFrame) {
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof MethodQueueUnbindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendUint8(strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $this->appendTable($frame->arguments, $buffer);
        } elseif ($frame instanceof MethodQueueUnbindOkFrame) {
        } elseif ($frame instanceof MethodBasicQosFrame) {
            $buffer->appendInt32($frame->prefetchSize);
            $buffer->appendInt16($frame->prefetchCount);
            $this->appendBits([$frame->global], $buffer);
        } elseif ($frame instanceof MethodBasicQosOkFrame) {
        } elseif ($frame instanceof MethodBasicConsumeFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendUint8(strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
            $this->appendBits([$frame->noLocal, $frame->noAck, $frame->exclusive, $frame->nowait], $buffer);
            $this->appendTable($frame->arguments, $buffer);
        } elseif ($frame instanceof MethodBasicConsumeOkFrame) {
            $buffer->appendUint8(strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
        } elseif ($frame instanceof MethodBasicCancelFrame) {
            $buffer->appendUint8(strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
            $this->appendBits([$frame->nowait], $buffer);
        } elseif ($frame instanceof MethodBasicCancelOkFrame) {
            $buffer->appendUint8(strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
        } elseif ($frame instanceof MethodBasicPublishFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $this->appendBits([$frame->mandatory, $frame->immediate], $buffer);
        } elseif ($frame instanceof MethodBasicReturnFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendUint8(strlen($frame->replyText)); $buffer->append($frame->replyText);
            $buffer->appendUint8(strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(strlen($frame->routingKey)); $buffer->append($frame->routingKey);
        } elseif ($frame instanceof MethodBasicDeliverFrame) {
            $buffer->appendUint8(strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
            $buffer->appendInt64($frame->deliveryTag);
            $this->appendBits([$frame->redelivered], $buffer);
            $buffer->appendUint8(strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(strlen($frame->routingKey)); $buffer->append($frame->routingKey);
        } elseif ($frame instanceof MethodBasicGetFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(strlen($frame->queue)); $buffer->append($frame->queue);
            $this->appendBits([$frame->noAck], $buffer);
        } elseif ($frame instanceof MethodBasicGetOkFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $this->appendBits([$frame->redelivered], $buffer);
            $buffer->appendUint8(strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof MethodBasicGetEmptyFrame) {
            $buffer->appendUint8(strlen($frame->clusterId)); $buffer->append($frame->clusterId);
        } elseif ($frame instanceof MethodBasicAckFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $this->appendBits([$frame->multiple], $buffer);
        } elseif ($frame instanceof MethodBasicRejectFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $this->appendBits([$frame->requeue], $buffer);
        } elseif ($frame instanceof MethodBasicRecoverAsyncFrame) {
            $this->appendBits([$frame->requeue], $buffer);
        } elseif ($frame instanceof MethodBasicRecoverFrame) {
            $this->appendBits([$frame->requeue], $buffer);
        } elseif ($frame instanceof MethodBasicRecoverOkFrame) {
        } elseif ($frame instanceof MethodBasicNackFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $this->appendBits([$frame->multiple, $frame->requeue], $buffer);
        } elseif ($frame instanceof MethodTxSelectFrame) {
        } elseif ($frame instanceof MethodTxSelectOkFrame) {
        } elseif ($frame instanceof MethodTxCommitFrame) {
        } elseif ($frame instanceof MethodTxCommitOkFrame) {
        } elseif ($frame instanceof MethodTxRollbackFrame) {
        } elseif ($frame instanceof MethodTxRollbackOkFrame) {
        } elseif ($frame instanceof MethodConfirmSelectFrame) {
            $this->appendBits([$frame->nowait], $buffer);
        } elseif ($frame instanceof MethodConfirmSelectOkFrame) {
        } else {
            throw new ProtocolException('Unhandled method frame ' . get_class($frame) . '.');
        }
    }

}
