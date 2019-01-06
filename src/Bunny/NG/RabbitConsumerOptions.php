<?php
namespace Bunny\NG;

/**
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class RabbitConsumerOptions
{

    /**
     * Queue name.
     *
     * @var string
     */
    public $queue;

    /**
     * Consumer tag.
     *
     * @var string
     */
    public $consumerTag;

    public static function new()
    {
        return new static();
    }

    /**
     * @param string $queue
     * @return self
     */
    public function setQueue(string $queue)
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * @param string $consumerTag
     * @return self
     */
    public function setConsumerTag(string $consumerTag)
    {
        $this->consumerTag = $consumerTag;
        return $this;
    }

}
