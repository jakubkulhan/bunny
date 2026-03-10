<?php

declare(strict_types=1);

namespace Bunny;

/**
 * AMQP constants.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class Constants
{
    // frame related constants
    public const FRAME_METHOD = 1;

    public const FRAME_HEADER = 2;

    public const FRAME_BODY = 3;

    public const FRAME_HEARTBEAT = 8;

    public const FRAME_MIN_SIZE = 4096;

    public const FRAME_END = 0xCE;

    public const FRAME_MAX = 0xFFFF;

    public const CHANNEL_MAX = 0xFFFF;

    // connection channel
    public const CONNECTION_CHANNEL = 0;

    // status codes
    public const STATUS_REPLY_SUCCESS = 200;

    public const STATUS_CONTENT_TOO_LARGE = 311;

    public const STATUS_NO_CONSUMERS = 313;

    public const STATUS_CONNECTION_FORCED = 320;

    public const STATUS_INVALID_PATH = 402;

    public const STATUS_ACCESS_REFUSED = 403;

    public const STATUS_NOT_FOUND = 404;

    public const STATUS_RESOURCE_LOCKED = 405;

    public const STATUS_PRECONDITION_FAILED = 406;

    public const STATUS_FRAME_ERROR = 501;

    public const STATUS_SYNTAX_ERROR = 502;

    public const STATUS_COMMAND_INVALID = 503;

    public const STATUS_CHANNEL_ERROR = 504;

    public const STATUS_UNEXPECTED_FRAME = 505;

    public const STATUS_RESOURCE_ERROR = 506;

    public const STATUS_NOT_ALLOWED = 530;

    public const STATUS_NOT_IMPLEMENTED = 540;

    public const STATUS_INTERNAL_ERROR = 541;

    // connection class
    public const CLASS_CONNECTION = 10;

    public const METHOD_CONNECTION_START = 10;

    public const METHOD_CONNECTION_START_OK = 11;

    public const METHOD_CONNECTION_SECURE = 20;

    public const METHOD_CONNECTION_SECURE_OK = 21;

    public const METHOD_CONNECTION_TUNE = 30;

    public const METHOD_CONNECTION_TUNE_OK = 31;

    public const METHOD_CONNECTION_OPEN = 40;

    public const METHOD_CONNECTION_OPEN_OK = 41;

    public const METHOD_CONNECTION_CLOSE = 50;

    public const METHOD_CONNECTION_CLOSE_OK = 51;

    public const METHOD_CONNECTION_BLOCKED = 60; // RabbitMQ extension

    public const METHOD_CONNECTION_UNBLOCKED = 61; // RabbitMQ extension

    // channel class
    public const CLASS_CHANNEL = 20;

    public const METHOD_CHANNEL_OPEN = 10;

    public const METHOD_CHANNEL_OPEN_OK = 11;

    public const METHOD_CHANNEL_FLOW = 20;

    public const METHOD_CHANNEL_FLOW_OK = 21;

    public const METHOD_CHANNEL_CLOSE = 40;

    public const METHOD_CHANNEL_CLOSE_OK = 41;

    // access class
    public const CLASS_ACCESS = 30;

    public const METHOD_ACCESS_REQUEST = 10;

    public const METHOD_ACCESS_REQUEST_OK = 11;

    // exchange class
    public const CLASS_EXCHANGE = 40;

    public const METHOD_EXCHANGE_DECLARE = 10;

    public const METHOD_EXCHANGE_DECLARE_OK = 11;

    public const METHOD_EXCHANGE_DELETE = 20;

    public const METHOD_EXCHANGE_DELETE_OK = 21;

    public const METHOD_EXCHANGE_BIND = 30; // RabbitMQ extension

    public const METHOD_EXCHANGE_BIND_OK = 31; // RabbitMQ extension

    public const METHOD_EXCHANGE_UNBIND = 40; // RabbitMQ extension

    public const METHOD_EXCHANGE_UNBIND_OK = 51; // RabbitMQ extension

    // queue class
    public const CLASS_QUEUE = 50;

    public const METHOD_QUEUE_DECLARE = 10;

    public const METHOD_QUEUE_DECLARE_OK = 11;

    public const METHOD_QUEUE_BIND = 20;

    public const METHOD_QUEUE_BIND_OK = 21;

    public const METHOD_QUEUE_PURGE = 30;

    public const METHOD_QUEUE_PURGE_OK = 31;

    public const METHOD_QUEUE_DELETE = 40;

    public const METHOD_QUEUE_DELETE_OK = 41;

    public const METHOD_QUEUE_UNBIND = 50;

    public const METHOD_QUEUE_UNBIND_OK = 51;

    // basic class
    public const CLASS_BASIC = 60;

    public const METHOD_BASIC_QOS = 10;

    public const METHOD_BASIC_QOS_OK = 11;

    public const METHOD_BASIC_CONSUME = 20;

    public const METHOD_BASIC_CONSUME_OK = 21;

    public const METHOD_BASIC_CANCEL = 30;

    public const METHOD_BASIC_CANCEL_OK = 31;

    public const METHOD_BASIC_PUBLISH = 40;

    public const METHOD_BASIC_RETURN = 50;

    public const METHOD_BASIC_DELIVER = 60;

    public const METHOD_BASIC_GET = 70;

    public const METHOD_BASIC_GET_OK = 71;

    public const METHOD_BASIC_GET_EMPTY = 72;

    public const METHOD_BASIC_ACK = 80;

    public const METHOD_BASIC_REJECT = 90;

    public const METHOD_BASIC_RECOVER_ASYNC = 100;

    public const METHOD_BASIC_RECOVER = 110;

    public const METHOD_BASIC_RECOVER_OK = 111;

    public const METHOD_BASIC_NACK = 120; // RabbitMQ extension

    // tx class
    public const CLASS_TX = 90;

    public const METHOD_TX_SELECT = 10;

    public const METHOD_TX_SELECT_OK = 11;

    public const METHOD_TX_COMMIT = 20;

    public const METHOD_TX_COMMIT_OK = 21;

    public const METHOD_TX_ROLLBACK = 30;

    public const METHOD_TX_ROLLBACK_OK = 31;

    // confirm class
    public const CLASS_CONFIRM = 85; // RabbitMQ extension

    public const METHOD_CONFIRM_SELECT = 10; // RabbitMQ extension

    public const METHOD_CONFIRM_SELECT_OK = 11; // RabbitMQ extension

    // table/array field value types
    public const FIELD_BOOLEAN = 0x74; // 't'

    public const FIELD_SHORT_SHORT_INT = 0x62; // 'b'

    public const FIELD_SHORT_SHORT_UINT = 0x42; // 'B'

    public const FIELD_SHORT_INT = 0x55; // 'U'

    public const FIELD_SHORT_UINT = 0x75; // 'u'

    public const FIELD_LONG_INT = 0x49; // 'I'

    public const FIELD_LONG_UINT = 0x69; // 'i'

    public const FIELD_LONG_LONG_INT = 0x4C; // 'L'

    public const FIELD_LONG_LONG_UINT = 0x6C; // 'l'

    public const FIELD_FLOAT = 0x66; // 'f'

    public const FIELD_DOUBLE = 0x64; // 'd'

    public const FIELD_DECIMAL_VALUE = 0x44; // 'D'

    public const FIELD_SHORT_STRING = 0x73; // 's'

    public const FIELD_LONG_STRING = 0x53; // 'S'

    public const FIELD_ARRAY = 0x41; // 'A'

    public const FIELD_TIMESTAMP = 0x54; // 'T'

    public const FIELD_TABLE = 0x46; // 'F'

    public const FIELD_NULL = 0x56; // 'V'
}
