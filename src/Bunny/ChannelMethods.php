<?php
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
     * 
     * @return AbstractClient
     */
    abstract public function getClient();

    /**
     * Returns channel id.
     * 
     * @return int
     */
    abstract public function getChannelId();

    /**
     * Calls exchange.declare AMQP method.
     *
     * @param string $exchange
     * @param string $exchangeType
     * @param boolean $passive
     * @param boolean $durable
     * @param boolean $autoDelete
     * @param boolean $internal
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodExchangeDeclareOkFrame
     */
    public function exchangeDeclare($exchange, $exchangeType = 'direct', $passive = false, $durable = false, $autoDelete = false, $internal = false, $nowait = false, $arguments = [])
    {
        return $this->getClient()->exchangeDeclare($this->getChannelId(), $exchange, $exchangeType, $passive, $durable, $autoDelete, $internal, $nowait, $arguments);
    }

    /**
     * Calls exchange.delete AMQP method.
     *
     * @param string $exchange
     * @param boolean $ifUnused
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodExchangeDeleteOkFrame
     */
    public function exchangeDelete($exchange, $ifUnused = false, $nowait = false)
    {
        return $this->getClient()->exchangeDelete($this->getChannelId(), $exchange, $ifUnused, $nowait);
    }

    /**
     * Calls exchange.bind AMQP method.
     *
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodExchangeBindOkFrame
     */
    public function exchangeBind($destination, $source, $routingKey = '', $nowait = false, $arguments = [])
    {
        return $this->getClient()->exchangeBind($this->getChannelId(), $destination, $source, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls exchange.unbind AMQP method.
     *
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodExchangeUnbindOkFrame
     */
    public function exchangeUnbind($destination, $source, $routingKey = '', $nowait = false, $arguments = [])
    {
        return $this->getClient()->exchangeUnbind($this->getChannelId(), $destination, $source, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls queue.declare AMQP method.
     *
     * @param string $queue
     * @param boolean $passive
     * @param boolean $durable
     * @param boolean $exclusive
     * @param boolean $autoDelete
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueueDeclareOkFrame
     */
    public function queueDeclare($queue = '', $passive = false, $durable = false, $exclusive = false, $autoDelete = false, $nowait = false, $arguments = [])
    {
        return $this->getClient()->queueDeclare($this->getChannelId(), $queue, $passive, $durable, $exclusive, $autoDelete, $nowait, $arguments);
    }

    /**
     * Calls queue.bind AMQP method.
     *
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueueBindOkFrame
     */
    public function queueBind($queue = '', $exchange, $routingKey = '', $nowait = false, $arguments = [])
    {
        return $this->getClient()->queueBind($this->getChannelId(), $queue, $exchange, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls queue.purge AMQP method.
     *
     * @param string $queue
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueuePurgeOkFrame
     */
    public function queuePurge($queue = '', $nowait = false)
    {
        return $this->getClient()->queuePurge($this->getChannelId(), $queue, $nowait);
    }

    /**
     * Calls queue.delete AMQP method.
     *
     * @param string $queue
     * @param boolean $ifUnused
     * @param boolean $ifEmpty
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueueDeleteOkFrame
     */
    public function queueDelete($queue = '', $ifUnused = false, $ifEmpty = false, $nowait = false)
    {
        return $this->getClient()->queueDelete($this->getChannelId(), $queue, $ifUnused, $ifEmpty, $nowait);
    }

    /**
     * Calls queue.unbind AMQP method.
     *
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueueUnbindOkFrame
     */
    public function queueUnbind($queue = '', $exchange, $routingKey = '', $arguments = [])
    {
        return $this->getClient()->queueUnbind($this->getChannelId(), $queue, $exchange, $routingKey, $arguments);
    }

    /**
     * Calls basic.qos AMQP method.
     *
     * @param int $prefetchSize
     * @param int $prefetchCount
     * @param boolean $global
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicQosOkFrame
     */
    public function qos($prefetchSize = 0, $prefetchCount = 0, $global = false)
    {
        return $this->getClient()->qos($this->getChannelId(), $prefetchSize, $prefetchCount, $global);
    }

    /**
     * Calls basic.consume AMQP method.
     *
     * @param string $queue
     * @param string $consumerTag
     * @param boolean $noLocal
     * @param boolean $noAck
     * @param boolean $exclusive
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicConsumeOkFrame
     */
    public function consume($queue = '', $consumerTag = '', $noLocal = false, $noAck = false, $exclusive = false, $nowait = false, $arguments = [])
    {
        return $this->getClient()->consume($this->getChannelId(), $queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);
    }

    /**
     * Calls basic.cancel AMQP method.
     *
     * @param string $consumerTag
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicCancelOkFrame
     */
    public function cancel($consumerTag, $nowait = false)
    {
        return $this->getClient()->cancel($this->getChannelId(), $consumerTag, $nowait);
    }

    /**
     * Calls basic.publish AMQP method.
     *
     * @param string $body
     * @param array $headers
     * @param string $exchange
     * @param string $routingKey
     * @param boolean $mandatory
     * @param boolean $immediate
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function publish($body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false)
    {
        return $this->getClient()->publish($this->getChannelId(), $body, $headers, $exchange, $routingKey, $mandatory, $immediate);
    }

    /**
     * Calls basic.get AMQP method.
     *
     * @param string $queue
     * @param boolean $noAck
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicGetOkFrame|Protocol\MethodBasicGetEmptyFrame
     */
    public function get($queue = '', $noAck = false)
    {
        return $this->getClient()->get($this->getChannelId(), $queue, $noAck);
    }

    /**
     * Calls basic.ack AMQP method.
     *
     * @param int $deliveryTag
     * @param boolean $multiple
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function ack($deliveryTag = 0, $multiple = false)
    {
        return $this->getClient()->ack($this->getChannelId(), $deliveryTag, $multiple);
    }

    /**
     * Calls basic.reject AMQP method.
     *
     * @param int $deliveryTag
     * @param boolean $requeue
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function reject($deliveryTag, $requeue = true)
    {
        return $this->getClient()->reject($this->getChannelId(), $deliveryTag, $requeue);
    }

    /**
     * Calls basic.recover-async AMQP method.
     *
     * @param boolean $requeue
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function recoverAsync($requeue = false)
    {
        return $this->getClient()->recoverAsync($this->getChannelId(), $requeue);
    }

    /**
     * Calls basic.recover AMQP method.
     *
     * @param boolean $requeue
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicRecoverOkFrame
     */
    public function recover($requeue = false)
    {
        return $this->getClient()->recover($this->getChannelId(), $requeue);
    }

    /**
     * Calls basic.nack AMQP method.
     *
     * @param int $deliveryTag
     * @param boolean $multiple
     * @param boolean $requeue
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function nack($deliveryTag = 0, $multiple = false, $requeue = true)
    {
        return $this->getClient()->nack($this->getChannelId(), $deliveryTag, $multiple, $requeue);
    }

    /**
     * Calls tx.select AMQP method.
     *
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodTxSelectOkFrame
     */
    public function txSelect()
    {
        return $this->getClient()->txSelect($this->getChannelId());
    }

    /**
     * Calls tx.commit AMQP method.
     *
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodTxCommitOkFrame
     */
    public function txCommit()
    {
        return $this->getClient()->txCommit($this->getChannelId());
    }

    /**
     * Calls tx.rollback AMQP method.
     *
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodTxRollbackOkFrame
     */
    public function txRollback()
    {
        return $this->getClient()->txRollback($this->getChannelId());
    }

    /**
     * Calls confirm.select AMQP method.
     *
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodConfirmSelectOkFrame
     */
    public function confirmSelect($nowait = false)
    {
        return $this->getClient()->confirmSelect($this->getChannelId(), $nowait);
    }

}
