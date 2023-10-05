<?php

declare(strict_types=1);

namespace Bunny;

use Bunny\Protocol;
use React\Promise;

/**
 * AMQP-0-9-1 channel methods
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
trait ChannelMethods
{

    /**
     * Returns underlying client instance.
     */
    abstract public function getClient(): Connection;

    /**
     * Returns channel id.
     */
    abstract public function getChannelId(): int;

    /**
     * Calls exchange.declare AMQP method.
     */
    public function exchangeDeclare(string $exchange, string $exchangeType = 'direct', bool $passive = false, bool $durable = false, bool $autoDelete = false, bool $internal = false, bool $nowait = false, array $arguments = []): bool|Protocol\MethodExchangeDeclareOkFrame
    {
        return $this->getClient()->exchangeDeclare($this->getChannelId(), $exchange, $exchangeType, $passive, $durable, $autoDelete, $internal, $nowait, $arguments);
    }

    /**
     * Calls exchange.delete AMQP method.
     */
    public function exchangeDelete(string $exchange, bool $ifUnused = false, bool $nowait = false): bool|Protocol\MethodExchangeDeleteOkFrame
    {
        return $this->getClient()->exchangeDelete($this->getChannelId(), $exchange, $ifUnused, $nowait);
    }

    /**
     * Calls exchange.bind AMQP method.
     */
    public function exchangeBind(string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): bool|Protocol\MethodExchangeBindOkFrame
    {
        return $this->getClient()->exchangeBind($this->getChannelId(), $destination, $source, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls exchange.unbind AMQP method.
     */
    public function exchangeUnbind(string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): bool|Protocol\MethodExchangeUnbindOkFrame
    {
        return $this->getClient()->exchangeUnbind($this->getChannelId(), $destination, $source, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls queue.declare AMQP method.
     */
    public function queueDeclare(string $queue = '', bool $passive = false, bool $durable = false, bool $exclusive = false, bool $autoDelete = false, bool $nowait = false, array $arguments = []): bool|Protocol\MethodQueueDeclareOkFrame
    {
        return $this->getClient()->queueDeclare($this->getChannelId(), $queue, $passive, $durable, $exclusive, $autoDelete, $nowait, $arguments);
    }

    /**
     * Calls queue.bind AMQP method.
     */
    public function queueBind(string $exchange, string $queue = '', string $routingKey = '', bool $nowait = false, array $arguments = []): bool|Protocol\MethodQueueBindOkFrame
    {
        return $this->getClient()->queueBind($this->getChannelId(), $exchange, $queue, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls queue.purge AMQP method.
     */
    public function queuePurge(string $queue = '', bool $nowait = false): bool|Protocol\MethodQueuePurgeOkFrame
    {
        return $this->getClient()->queuePurge($this->getChannelId(), $queue, $nowait);
    }

    /**
     * Calls queue.delete AMQP method.
     */
    public function queueDelete(string $queue = '', bool $ifUnused = false, bool $ifEmpty = false, bool $nowait = false): bool|Protocol\MethodQueueDeleteOkFrame
    {
        return $this->getClient()->queueDelete($this->getChannelId(), $queue, $ifUnused, $ifEmpty, $nowait);
    }

    /**
     * Calls queue.unbind AMQP method.
     */
    public function queueUnbind(string $exchange, string $queue = '', string $routingKey = '', array $arguments = []): bool|Protocol\MethodQueueUnbindOkFrame
    {
        return $this->getClient()->queueUnbind($this->getChannelId(), $exchange, $queue, $routingKey, $arguments);
    }

    /**
     * Calls basic.qos AMQP method.
     */
    public function qos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): bool|Protocol\MethodBasicQosOkFrame
    {
        return $this->getClient()->qos($this->getChannelId(), $prefetchSize, $prefetchCount, $global);
    }

    /**
     * Calls basic.consume AMQP method.
     */
    public function consume(string $queue = '', string $consumerTag = '', bool $noLocal = false, bool $noAck = false, bool $exclusive = false, bool $nowait = false, array $arguments = []): bool|Protocol\MethodBasicConsumeOkFrame
    {
        return $this->getClient()->consume($this->getChannelId(), $queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);
    }

    /**
     * Calls basic.cancel AMQP method.
     */
    public function cancel(string $consumerTag, bool $nowait = false): bool|Protocol\MethodBasicCancelOkFrame
    {
        return $this->getClient()->cancel($this->getChannelId(), $consumerTag, $nowait);
    }

    /**
     * Calls basic.publish AMQP method.
     */
    public function publish(string $body, array $headers = [], string $exchange = '', string $routingKey = '', bool $mandatory = false, bool $immediate = false): bool
    {
        return $this->getClient()->publish($this->getChannelId(), $body, $headers, $exchange, $routingKey, $mandatory, $immediate);
    }

    /**
     * Calls basic.get AMQP method.
     */
    public function get(string $queue = '', bool $noAck = false): bool|Protocol\MethodBasicGetOkFrame|Protocol\MethodBasicGetEmptyFrame
    {
        return $this->getClient()->get($this->getChannelId(), $queue, $noAck);
    }

    /**
     * Calls basic.ack AMQP method.
     */
    public function ack(int $deliveryTag = 0, bool $multiple = false): bool
    {
        return $this->getClient()->ack($this->getChannelId(), $deliveryTag, $multiple);
    }

    /**
     * Calls basic.reject AMQP method.
     */
    public function reject(int $deliveryTag, bool $requeue = true): bool
    {
        return $this->getClient()->reject($this->getChannelId(), $deliveryTag, $requeue);
    }

    /**
     * Calls basic.recover-async AMQP method.
     */
    public function recoverAsync(bool $requeue = false): bool
    {
        return $this->getClient()->recoverAsync($this->getChannelId(), $requeue);
    }

    /**
     * Calls basic.recover AMQP method.
     */
    public function recover(bool $requeue = false): bool|Protocol\MethodBasicRecoverOkFrame
    {
        return $this->getClient()->recover($this->getChannelId(), $requeue);
    }

    /**
     * Calls basic.nack AMQP method.
     */
    public function nack(int $deliveryTag = 0, bool $multiple = false, bool $requeue = true): bool
    {
        return $this->getClient()->nack($this->getChannelId(), $deliveryTag, $multiple, $requeue);
    }

    /**
     * Calls tx.select AMQP method.
     */
    public function txSelect(): bool|Protocol\MethodTxSelectOkFrame
    {
        return $this->getClient()->txSelect($this->getChannelId());
    }

    /**
     * Calls tx.commit AMQP method.
     */
    public function txCommit(): bool|Protocol\MethodTxCommitOkFrame
    {
        return $this->getClient()->txCommit($this->getChannelId());
    }

    /**
     * Calls tx.rollback AMQP method.
     */
    public function txRollback(): bool|Protocol\MethodTxRollbackOkFrame
    {
        return $this->getClient()->txRollback($this->getChannelId());
    }

    /**
     * Calls confirm.select AMQP method.
     */
    public function confirmSelect(bool $nowait = false): bool|Protocol\MethodConfirmSelectOkFrame
    {
        return $this->getClient()->confirmSelect($this->getChannelId(), $nowait);
    }

}
