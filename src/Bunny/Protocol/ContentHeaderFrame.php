<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * Content header AMQP frame.
 *
 * Frame's payload wire format:
 *
 *
 *         0          2        4           12      14
 *     ----+----------+--------+-----------+-------+-----------------
 *     ... | class-id | weight | body-size | flags | property-list...
 *     ----+----------+--------+-----------+-------+-----------------
 *            uint16    uint16    uint64     uint16
 *
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ContentHeaderFrame extends AbstractFrame
{

    const FLAG_CONTENT_TYPE = 0x8000;

    const FLAG_CONTENT_ENCODING = 0x4000;

    const FLAG_HEADERS = 0x2000;

    const FLAG_DELIVERY_MODE = 0x1000;

    const FLAG_PRIORITY = 0x0800;

    const FLAG_CORRELATION_ID = 0x0400;

    const FLAG_REPLY_TO = 0x0200;

    const FLAG_EXPIRATION = 0x0100;

    const FLAG_MESSAGE_ID = 0x0080;

    const FLAG_TIMESTAMP = 0x0040;

    const FLAG_TYPE = 0x0020;

    const FLAG_USER_ID = 0x0010;

    const FLAG_APP_ID = 0x0008;

    const FLAG_CLUSTER_ID = 0x0004;

    /** @var int */
    public $classId = Constants::CLASS_BASIC;

    /** @var int */
    public $weight = 0;

    /** @var int */
    public $bodySize;

    /** @var int */
    public $flags = 0;

    /** @var string */
    public $contentType;

    /** @var string */
    public $contentEncoding;

    /** @var array */
    public $headers;

    /** @var int */
    public $deliveryMode;

    /** @var int */
    public $priority;

    /** @var string */
    public $correlationId;

    /** @var string */
    public $replyTo;

    /** @var string */
    public $expiration;

    /** @var string */
    public $messageId;

    /** @var \DateTime */
    public $timestamp;

    /** @var string */
    public $typeHeader;

    /** @var string */
    public $userId;

    /** @var string */
    public $appId;

    /** @var string */
    public $clusterId;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(Constants::FRAME_HEADER);
    }

    /**
     * Creates frame from array.
     *
     * @param array $headers
     * @return ContentHeaderFrame
     */
    public static function fromArray(array $headers)
    {
        $instance = new static();

        if (isset($headers["content-type"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_CONTENT_TYPE;
            $instance->contentType = $headers["content-type"];
            unset($headers["content-type"]);
        }

        if (isset($headers["content-encoding"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_CONTENT_ENCODING;
            $instance->contentEncoding = $headers["content-encoding"];
            unset($headers["content-encoding"]);
        }

        if (isset($headers["delivery-mode"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_DELIVERY_MODE;
            $instance->deliveryMode = $headers["delivery-mode"];
            unset($headers["delivery-mode"]);
        }

        if (isset($headers["priority"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_PRIORITY;
            $instance->priority = $headers["priority"];
            unset($headers["priority"]);
        }

        if (isset($headers["correlation-id"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_CORRELATION_ID;
            $instance->correlationId = $headers["correlation-id"];
            unset($headers["correlation-id"]);
        }

        if (isset($headers["reply-to"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_REPLY_TO;
            $instance->replyTo = $headers["reply-to"];
            unset($headers["reply-to"]);
        }

        if (isset($headers["expiration"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_EXPIRATION;
            $instance->expiration = $headers["expiration"];
            unset($headers["expiration"]);
        }

        if (isset($headers["message-id"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_MESSAGE_ID;
            $instance->messageId = $headers["message-id"];
            unset($headers["message-id"]);
        }

        if (isset($headers["timestamp"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_TIMESTAMP;
            $instance->timestamp = $headers["timestamp"];
            unset($headers["timestamp"]);
        }

        if (isset($headers["type"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_TYPE;
            $instance->typeHeader = $headers["type"];
            unset($headers["type"]);
        }

        if (isset($headers["user-id"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_USER_ID;
            $instance->userId = $headers["user-id"];
            unset($headers["user-id"]);
        }

        if (isset($headers["app-id"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_APP_ID;
            $instance->appId = $headers["app-id"];
            unset($headers["app-id"]);
        }

        if (isset($headers["cluster-id"])) {
            $instance->flags |= ContentHeaderFrame::FLAG_CLUSTER_ID;
            $instance->clusterId = $headers["cluster-id"];
            unset($headers["cluster-id"]);
        }

        if (!empty($headers)) {
            $instance->flags |= ContentHeaderFrame::FLAG_HEADERS;
            $instance->headers = $headers;
        }

        return $instance;
    }

    /**
     * Inverse function of {@link fromArray()}
     *
     * @return array
     */
    public function toArray()
    {
        $headers = $this->headers;

        if ($headers === null) {
            $headers = [];
        }

        if ($this->contentType !== null) {
            $headers["content-type"] = $this->contentType;
        }

        if ($this->contentEncoding !== null) {
            $headers["content-encoding"] = $this->contentEncoding;
        }

        if ($this->deliveryMode !== null) {
            $headers["delivery-mode"] = $this->deliveryMode;
        }

        if ($this->priority !== null) {
            $headers["priority"] = $this->priority;
        }

        if ($this->correlationId !== null) {
            $headers["correlation-id"] = $this->correlationId;
        }

        if ($this->replyTo !== null) {
            $headers["reply-to"] = $this->replyTo;
        }

        if ($this->expiration !== null) {
            $headers["expiration"] = $this->expiration;
        }

        if ($this->messageId !== null) {
            $headers["message-id"] = $this->messageId;
        }

        if ($this->timestamp !== null) {
            $headers["timestamp"] = $this->timestamp;
        }

        if ($this->typeHeader !== null) {
            $headers["type"] = $this->typeHeader;
        }

        if ($this->userId !== null) {
            $headers["user-id"] = $this->userId;
        }

        if ($this->appId !== null) {
            $headers["app-id"] = $this->appId;
        }

        if ($this->clusterId !== null) {
            $headers["cluster-id"] = $this->clusterId;
        }

        return $headers;
    }

}
