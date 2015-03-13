<?php
namespace Bunny;

/**
 * Convenience crate for transferring messages through app.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Message
{

    /** @var string */
    public $consumerTag;

    /** @var int */
    public $deliveryTag;

    /** @var boolean */
    public $redelivered;

    /** @var string */
    public $exchange;

    /** @var string */
    public $routingKey;

    /** @var array */
    public $headers;

    /** @var string */
    public $content;

    /**
     * Constructor.
     *
     * @param string $consumerTag
     * @param string $deliveryTag
     * @param boolean $redelivered
     * @param string $exchange
     * @param string $routingKey
     * @param array $headers
     * @param string $content
     */
    public function __construct($consumerTag, $deliveryTag, $redelivered, $exchange, $routingKey, array $headers, $content)
    {
        $this->consumerTag = $consumerTag;
        $this->deliveryTag = $deliveryTag;
        $this->redelivered = $redelivered;
        $this->exchange = $exchange;
        $this->routingKey = $routingKey;
        $this->headers = $headers;
        $this->content = $content;
    }

    /**
     * Returns header or default value.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getHeader($name, $default = null)
    {
        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        } else {
            return $default;
        }
    }

    /**
     * Returns TRUE if message has given header.
     *
     * @param string $name
     * @return boolean
     */
    public function hasHeader($name)
    {
        return isset($this->headers[$name]);
    }

}
