<?php

declare(strict_types=1);

namespace Bunny\Test;


use Bunny\ChannelInterface;
use Bunny\ChannelMode;
use Bunny\ClientInterface;
use Bunny\Message;
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
use Evenement\EventEmitterTrait;

final class MockChannelInterface implements ChannelInterface
{
    use EventEmitterTrait;

    public function getMode(): ChannelMode
    {
        return ChannelMode::Regular;
    }

    public function addReturnListener(callable $callback): ChannelInterface
    {
        return $this;
    }

    public function removeReturnListener(callable $callback): ChannelInterface
    {
        return $this;
    }

    public function addAckListener(callable $callback): ChannelInterface
    {
        return $this;
    }

    public function removeAckListener(callable $callback): ChannelInterface
    {
        return $this;
    }

    public function close(int $replyCode = 0, string $replyText = '', bool $connectionStatus = ClientInterface::RAW_CONNECTION_ACTIVE): void
    {
    }

    public function consume(callable $callback, string $queue = '', string $consumerTag = '', bool $noLocal = false, bool $noAck = false, bool $exclusive = false, bool $nowait = false, array $arguments = [], int $concurrency = 1): MethodBasicConsumeOkFrame
    {
        return new MethodBasicConsumeOkFrame();
    }

    public function ack(Message $message, bool $multiple = false): bool
    {
        return false;
    }

    public function nack(Message $message, bool $multiple = false, bool $requeue = true): bool
    {
        return false;
    }

    public function reject(Message $message, bool $requeue = true): bool
    {
        return false;
    }

    public function get(string $queue = '', bool $noAck = false): Message|null
    {
        return null;
    }

    public function publish(string $body, array $headers = [], string $exchange = '', string $routingKey = '', bool $mandatory = false, bool $immediate = false): int|bool
    {
        return false;
    }

    public function cancel(string $consumerTag, bool $nowait = false): MethodBasicCancelOkFrame|bool
    {
        return false;
    }

    public function txSelect(): MethodTxSelectOkFrame
    {
        return new MethodTxSelectOkFrame();
    }

    public function txCommit(): MethodTxCommitOkFrame
    {
        return new MethodTxCommitOkFrame();
    }

    public function txRollback(): MethodTxRollbackOkFrame
    {
        return new MethodTxRollbackOkFrame();
    }

    public function confirmSelect(?callable $callback = null, bool $nowait = false): MethodConfirmSelectOkFrame|bool
    {
        return false;
    }

    public function qos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): MethodBasicQosOkFrame
    {
        return new MethodBasicQosOkFrame();
    }

    public function queueDeclare(string $queue = '', bool $passive = false, bool $durable = false, bool $exclusive = false, bool $autoDelete = false, bool $nowait = false, array $arguments = []): MethodQueueDeclareOkFrame|bool
    {
        return false;
    }

    public function queueBind(string $exchange, string $queue = '', string $routingKey = '', bool $nowait = false, array $arguments = []): MethodQueueBindOkFrame|bool
    {
        return false;
    }

    public function queuePurge(string $queue = '', bool $nowait = false): MethodQueuePurgeOkFrame|bool
    {
        return false;
    }

    public function queueDelete(string $queue = '', bool $ifUnused = false, bool $ifEmpty = false, bool $nowait = false): MethodQueueDeleteOkFrame|bool
    {
        return false;
    }

    public function queueUnbind(string $exchange, string $queue = '', string $routingKey = '', array $arguments = []): MethodQueueUnbindOkFrame
    {
        return new MethodQueueUnbindOkFrame();
    }

    public function exchangeDeclare(string $exchange, string $exchangeType = 'direct', bool $passive = false, bool $durable = false, bool $autoDelete = false, bool $internal = false, bool $nowait = false, array $arguments = []): MethodExchangeDeclareOkFrame|bool
    {
        return false;
    }

    public function exchangeDelete(string $exchange, bool $ifUnused = false, bool $nowait = false): MethodExchangeDeleteOkFrame|bool
    {
        return false;
    }

    public function exchangeBind(string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): MethodExchangeBindOkFrame|bool
    {
        return false;
    }

    public function exchangeUnbind(string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): MethodExchangeUnbindOkFrame|bool
    {
        return false;
    }

    public function recoverAsync(bool $requeue = false): bool
    {
        return false;
    }

    public function recover(bool $requeue = false): MethodBasicRecoverOkFrame
    {
        return new MethodBasicRecoverOkFrame();
    }

}
