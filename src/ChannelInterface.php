<?php

declare(strict_types=1);

namespace Bunny;

use Bunny\Protocol\MethodBasicCancelOkFrame;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicQosOkFrame;
use Bunny\Protocol\MethodBasicRecoverOkFrame;
use Bunny\Protocol\MethodConfirmSelectOkFrame;
use Bunny\Protocol\MethodExchangeBindOkFrame;
use Bunny\Protocol\MethodExchangeDeclareOkFrame;
use Bunny\Protocol\MethodExchangeDeleteOkFrame;
use Bunny\Protocol\MethodExchangeUnbindOkFrame;
use Bunny\Protocol\MethodQueueBindOkFrame;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use Bunny\Protocol\MethodQueueDeleteOkFrame;
use Bunny\Protocol\MethodQueuePurgeOkFrame;
use Bunny\Protocol\MethodQueueUnbindOkFrame;
use Bunny\Protocol\MethodTxCommitOkFrame;
use Bunny\Protocol\MethodTxRollbackOkFrame;
use Bunny\Protocol\MethodTxSelectOkFrame;

/**
 * AMQP channel.
 *
 * - Closely works with underlying client instance.
 * - Manages consumers.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 * @final Will be marked final in a future major release
 */
interface ChannelInterface
{
    /**
     * Returns the channel mode.
     */
    public function getMode(): ChannelMode;

    /**
     * Listener is called whenever 'basic.return' frame is received with arguments (Message $returnedMessage, MethodBasicReturnFrame $frame)
     *
     * @param callable(\Bunny\Message, \Bunny\Protocol\MethodBasicReturnFrame): void $callback
     */
    public function addReturnListener(callable $callback): self;

    /**
     * Removes registered return listener. If the callback is not registered, this is noop.
     *
     * @param callable(\Bunny\Message, \Bunny\Protocol\MethodBasicReturnFrame): void $callback
     */
    public function removeReturnListener(callable $callback): self;

    /**
     * Listener is called whenever 'basic.ack' or 'basic.nack' is received.
     *
     * @param callable(\Bunny\Protocol\MethodBasicAckFrame|\Bunny\Protocol\MethodBasicNackFrame): void $callback
     */
    public function addAckListener(callable $callback): self;

    /**
     * Removes registered ack/nack listener. If the callback is not registered, this is noop.
     *
     * @param callable(\Bunny\Protocol\MethodBasicAckFrame|\Bunny\Protocol\MethodBasicNackFrame): void $callback
     */
    public function removeAckListener(callable $callback): self;

    /**
     * Closes channel.
     *
     * Always returns a promise, because there can be outstanding messages to be processed.
     */
    public function close(int $replyCode = 0, string $replyText = '', bool $byServer = false): void;

    /**
     * Creates new consumer on channel.
     *
     * @param array<string,mixed> $arguments
     * @param positive-int        $concurrency
     */
    public function consume(callable $callback, string $queue = '', string $consumerTag = '', bool $noLocal = false, bool $noAck = false, bool $exclusive = false, bool $nowait = false, array $arguments = [], int $concurrency = 1): MethodBasicConsumeOkFrame;

    /**
     * Acks given message.
     *
     * @return false
     */
    public function ack(Message $message, bool $multiple = false): bool;

    /**
     * Nacks given message.
     *
     * @return false
     */
    public function nack(Message $message, bool $multiple = false, bool $requeue = true): bool;

    /**
     * Rejects given message.
     *
     * @return false
     */
    public function reject(Message $message, bool $requeue = true): bool;

    /**
     * Synchronously returns message if there is any waiting in the queue.
     */
    public function get(string $queue = '', bool $noAck = false): Message|null;

    /**
     * Published message to given exchange.
     *
     * @param array<string,mixed> $headers
     *
     * @return int|false
     */
    public function publish(string $body, array $headers = [], string $exchange = '', string $routingKey = '', bool $mandatory = false, bool $immediate = false): int|bool;

    /**
     * Cancels given consumer subscription.
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodBasicCancelOkFrame : false)
     */
    public function cancel(string $consumerTag, bool $nowait = false): MethodBasicCancelOkFrame|bool;

    /**
     * Changes channel to transactional mode. All messages are published to queues only after {@link txCommit()} is called.
     */
    public function txSelect(): MethodTxSelectOkFrame;

    /**
     * Commit transaction.
     */
    public function txCommit(): MethodTxCommitOkFrame;

    /**
     * Rollback transaction.
     */
    public function txRollback(): MethodTxRollbackOkFrame;

    /**
     * Changes channel to confirm mode. Broker then asynchronously sends 'basic.ack's for published messages.
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodConfirmSelectOkFrame : false)
     */
    public function confirmSelect(?callable $callback = null, bool $nowait = false): MethodConfirmSelectOkFrame|bool;

    /**
     * Calls basic.qos AMQP method.
     */
    public function qos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): MethodBasicQosOkFrame;

    /**
     * Calls queue.declare AMQP method.
     *
     * @param array<string,mixed> $arguments
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodQueueDeclareOkFrame : false)
     */
    public function queueDeclare(string $queue = '', bool $passive = false, bool $durable = false, bool $exclusive = false, bool $autoDelete = false, bool $nowait = false, array $arguments = []): MethodQueueDeclareOkFrame|bool;

    /**
     * Calls queue.bind AMQP method.
     *
     * @param array<string,mixed> $arguments
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodQueueBindOkFrame : false)
     */
    public function queueBind(string $exchange, string $queue = '', string $routingKey = '', bool $nowait = false, array $arguments = []): MethodQueueBindOkFrame|bool;

    /**
     * Calls queue.purge AMQP method.
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodQueuePurgeOkFrame : false)
     */
    public function queuePurge(string $queue = '', bool $nowait = false): MethodQueuePurgeOkFrame|bool;

    /**
     * Calls queue.delete AMQP method.
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodQueueDeleteOkFrame : false)
     */
    public function queueDelete(string $queue = '', bool $ifUnused = false, bool $ifEmpty = false, bool $nowait = false): MethodQueueDeleteOkFrame|bool;

    /**
     * Calls queue.unbind AMQP method.
     *
     * @param array<string,mixed> $arguments
     */
    public function queueUnbind(string $exchange, string $queue = '', string $routingKey = '', array $arguments = []): MethodQueueUnbindOkFrame;

    /**
     * Calls exchange.declare AMQP method.
     *
     * @param array<string,mixed> $arguments
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodExchangeDeclareOkFrame : false)
     */
    public function exchangeDeclare(string $exchange, string $exchangeType = 'direct', bool $passive = false, bool $durable = false, bool $autoDelete = false, bool $internal = false, bool $nowait = false, array $arguments = []): MethodExchangeDeclareOkFrame|bool;

    /**
     * Calls exchange.delete AMQP method.
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodExchangeDeleteOkFrame : false)
     */
    public function exchangeDelete(string $exchange, bool $ifUnused = false, bool $nowait = false): MethodExchangeDeleteOkFrame|bool;

    /**
     * Calls exchange.bind AMQP method.
     *
     * @param array<string,mixed> $arguments
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodExchangeBindOkFrame : false)
     */
    public function exchangeBind(string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): MethodExchangeBindOkFrame|bool;

    /**
     * Calls exchange.unbind AMQP method.
     *
     * @param array<string,mixed> $arguments
     *
     * @return ($nowait is false ? \Bunny\Protocol\MethodExchangeUnbindOkFrame : false)
     */
    public function exchangeUnbind(string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): MethodExchangeUnbindOkFrame|bool;

    /**
     * Calls basic.recover-async AMQP method.
     *
     * @return false
     */
    public function recoverAsync(bool $requeue = false): bool;

    /**
     * Calls basic.recover AMQP method.
     */
    public function recover(bool $requeue = false): MethodBasicRecoverOkFrame;
}
