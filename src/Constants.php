<?php
namespace Bunny;

/**
 * AMQP constants.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class Constants
{

    // frame related constants
    const FRAME_METHOD = 1;

    const FRAME_HEADER = 2;

    const FRAME_BODY = 3;

    const FRAME_HEARTBEAT = 8;

    const FRAME_MIN_SIZE = 4096;

    const FRAME_END = 0xCE;

    // connection channel
    const CONNECTION_CHANNEL = 0;

    // status codes
    const STATUS_REPLY_SUCCESS = 200;

    const STATUS_CONTENT_TOO_LARGE = 311;

    const STATUS_NO_CONSUMERS = 313;

    const STATUS_CONNECTION_FORCED = 320;

    const STATUS_INVALID_PATH = 402;

    const STATUS_ACCESS_REFUSED = 403;

    const STATUS_NOT_FOUND = 404;

    const STATUS_RESOURCE_LOCKED = 405;

    const STATUS_PRECONDITION_FAILED = 406;

    const STATUS_FRAME_ERROR = 501;

    const STATUS_SYNTAX_ERROR = 502;

    const STATUS_COMMAND_INVALID = 503;

    const STATUS_CHANNEL_ERROR = 504;

    const STATUS_UNEXPECTED_FRAME = 505;

    const STATUS_RESOURCE_ERROR = 506;

    const STATUS_NOT_ALLOWED = 530;

    const STATUS_NOT_IMPLEMENTED = 540;

    const STATUS_INTERNAL_ERROR = 541;

    // connection class
    const CLASS_CONNECTION = 10;

    const METHOD_CONNECTION_START = 10;

    const METHOD_CONNECTION_START_OK = 11;

    const METHOD_CONNECTION_SECURE = 20;

    const METHOD_CONNECTION_SECURE_OK = 21;

    const METHOD_CONNECTION_TUNE = 30;

    const METHOD_CONNECTION_TUNE_OK = 31;

    const METHOD_CONNECTION_OPEN = 40;

    const METHOD_CONNECTION_OPEN_OK = 41;

    const METHOD_CONNECTION_CLOSE = 50;

    const METHOD_CONNECTION_CLOSE_OK = 51;

    const METHOD_CONNECTION_BLOCKED = 60; // RabbitMQ extension

    const METHOD_CONNECTION_UNBLOCKED = 61; // RabbitMQ extension

    // channel class
    const CLASS_CHANNEL = 20;

    const METHOD_CHANNEL_OPEN = 10;

    const METHOD_CHANNEL_OPEN_OK = 11;

    const METHOD_CHANNEL_FLOW = 20;

    const METHOD_CHANNEL_FLOW_OK = 21;

    const METHOD_CHANNEL_CLOSE = 40;

    const METHOD_CHANNEL_CLOSE_OK = 41;

    // access class
    const CLASS_ACCESS = 30;

    const METHOD_ACCESS_REQUEST = 10;

    const METHOD_ACCESS_REQUEST_OK = 11;

    // exchange class
    const CLASS_EXCHANGE = 40;

    const METHOD_EXCHANGE_DECLARE = 10;

    const METHOD_EXCHANGE_DECLARE_OK = 11;

    const METHOD_EXCHANGE_DELETE = 20;

    const METHOD_EXCHANGE_DELETE_OK = 21;

    const METHOD_EXCHANGE_BIND = 30; // RabbitMQ extension

    const METHOD_EXCHANGE_BIND_OK = 31; // RabbitMQ extension

    const METHOD_EXCHANGE_UNBIND = 40; // RabbitMQ extension

    const METHOD_EXCHANGE_UNBIND_OK = 51; // RabbitMQ extension

    // queue class
    const CLASS_QUEUE = 50;

    const METHOD_QUEUE_DECLARE = 10;

    const METHOD_QUEUE_DECLARE_OK = 11;

    const METHOD_QUEUE_BIND = 20;

    const METHOD_QUEUE_BIND_OK = 21;

    const METHOD_QUEUE_PURGE = 30;

    const METHOD_QUEUE_PURGE_OK = 31;

    const METHOD_QUEUE_DELETE = 40;

    const METHOD_QUEUE_DELETE_OK = 41;

    const METHOD_QUEUE_UNBIND = 50;

    const METHOD_QUEUE_UNBIND_OK = 51;

    // basic class
    const CLASS_BASIC = 60;

    const METHOD_BASIC_QOS = 10;

    const METHOD_BASIC_QOS_OK = 11;

    const METHOD_BASIC_CONSUME = 20;

    const METHOD_BASIC_CONSUME_OK = 21;

    const METHOD_BASIC_CANCEL = 30;

    const METHOD_BASIC_CANCEL_OK = 31;

    const METHOD_BASIC_PUBLISH = 40;

    const METHOD_BASIC_RETURN = 50;

    const METHOD_BASIC_DELIVER = 60;

    const METHOD_BASIC_GET = 70;

    const METHOD_BASIC_GET_OK = 71;

    const METHOD_BASIC_GET_EMPTY = 72;

    const METHOD_BASIC_ACK = 80;

    const METHOD_BASIC_REJECT = 90;

    const METHOD_BASIC_RECOVER_ASYNC = 100;

    const METHOD_BASIC_RECOVER = 110;

    const METHOD_BASIC_RECOVER_OK = 111;

    const METHOD_BASIC_NACK = 120; // RabbitMQ extension

    // tx class
    const CLASS_TX = 90;

    const METHOD_TX_SELECT = 10;

    const METHOD_TX_SELECT_OK = 11;

    const METHOD_TX_COMMIT = 20;

    const METHOD_TX_COMMIT_OK = 21;

    const METHOD_TX_ROLLBACK = 30;

    const METHOD_TX_ROLLBACK_OK = 31;

    // confirm class
    const CLASS_CONFIRM = 85; // RabbitMQ extension

    const METHOD_CONFIRM_SELECT = 10; // RabbitMQ extension

    const METHOD_CONFIRM_SELECT_OK = 11; // RabbitMQ extension

    // table/array field value types
    const FIELD_BOOLEAN = 0x74; // 't'

    const FIELD_SHORT_SHORT_INT = 0x62; // 'b'

    const FIELD_SHORT_SHORT_UINT = 0x42; // 'B'

    const FIELD_SHORT_INT = 0x55; // 'U'

    const FIELD_SHORT_UINT = 0x75; // 'u'

    const FIELD_LONG_INT = 0x49; // 'I'

    const FIELD_LONG_UINT = 0x69; // 'i'

    const FIELD_LONG_LONG_INT = 0x4C; // 'L'

    const FIELD_LONG_LONG_UINT = 0x6C; // 'l'

    const FIELD_FLOAT = 0x66; // 'f'

    const FIELD_DOUBLE = 0x64; // 'd'

    const FIELD_DECIMAL_VALUE = 0x44; // 'D'

    const FIELD_SHORT_STRING = 0x73; // 's'

    const FIELD_LONG_STRING = 0x53; // 'S'

    const FIELD_ARRAY = 0x41; // 'A'

    const FIELD_TIMESTAMP = 0x54; // 'T'

    const FIELD_TABLE = 0x46; // 'F'

    const FIELD_NULL = 0x56; // 'V'

}
