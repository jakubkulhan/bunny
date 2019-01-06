<?php
namespace Bunny\NG;

/**
 * Client manages message publications and subscriptions.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
interface RabbitClientInterface
{

    /**
     * Publishes the message on the client-managed connection.
     *
     * If the client is not connected, a new connection will be opened.
     *
     * If the connection gets broken, a new one be opened.
     *
     * All publications use the same channel on the connection.
     *
     * @param RabbitMessage $message
     * @return void
     */
    public function publish(RabbitMessage $message);

    /**
     * Consume from one or more queues on the client-managed connection.
     *
     * If the client is not connected, a new connection will be opened.
     *
     * If the connection gets broken and there are messages that has been handed over to the application, however,
     * that weren't acknowledged, rejected, or nacked, an exception will be thrown. Otherwise a new connection will
     * be opened.
     *
     * Every subscription gets a new channel. However, all consumers of the subscription share that channel.
     *
     * @return RabbitSubscriptionInterface
     */
    public function subscribe(): RabbitSubscriptionInterface;

    /**
     * Send heartbeat on the client-managed connection.
     *
     * If the client is not connected, a new connection will be opened.
     *
     * This method is supposed to be called either if the application tries to verify that the broker is available,
     * or it received a message on a subscription and it knows that the heartbeat timeout would be triggered. Otherwise,
     * e.g. when a subscription waits for new messages, heartbeats get sent automatically.
     *
     * @return void
     */
    public function ping();

}
