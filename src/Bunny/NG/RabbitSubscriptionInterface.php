<?php
namespace Bunny\NG;

/**
 * Subscription iterates over messages from registered consumers.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
interface RabbitSubscriptionInterface extends \Iterator, ClosableInterface
{

    /**
     * @param int $prefetchCount
     * @return self
     */
    public function setPrefetchCount(int $prefetchCount);

    /**
     * @param string $queueName
     * @param string|null $consumerTag
     * @return self
     */
    public function add(string $queueName, ?string &$consumerTag = null);

    /**
     * @param string $consumerTag
     * @return self
     */
    public function remove(string $consumerTag);

}
