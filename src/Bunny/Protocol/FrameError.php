<?php

namespace Bunny\Protocol;

use Bunny\Exception\BunnyException;
use Bunny\Exception\ChannelException;
use Bunny\Exception\ClientException;

/**
 * AMQP-0-9-1 Error fetcher. 
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * Returns the appropriate exception instance based on the frame type.
 *
 * For MethodChannelCloseFrame, MethodConnectionCloseFrame and MethodBasicReturnFrame frames
 * a unique SoftError???Exception or HardError???Exception will be returned as these
 * frames each have a replyCode and replyText property.
 *
 * For all other frames either a ClientException or ChannelException will be returned, depending
 * on the frame's class id.
 *
 * The Frame's class and method properties is appended to the default message (if one was supplied).
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */

class FrameError
{
    /**
     * Returns the appropriate exception for the response frame.
     *
     * @param MethodFrame $frame
     * @param string      $defaultErrorMsg
     *
     * @return \Bunny\Exception\BunnyException
     */
    public function get(MethodFrame $frame, $defaultErrorMsg = '')
    {
        $className = get_class($frame);
        $class = $this->getClass($frame->classId);
        if ($class === null) {
            $amqpClassName = 'unknown';
            $amqpMethodName = (string)$frame->methodId;
            $classType = (string)$frame->classId;
        } else {
            $amqpClassName = $class['name'];
            $amqpMethodName = $this->getClassMethod($class, $frame->methodId, 'unknown');
            $classType = $class['type'];
        }
        if ($frame instanceof MethodChannelCloseFrame ||
            $frame instanceof MethodConnectionCloseFrame ||
            $frame instanceof MethodBasicReturnFrame) {

            $errorCode = $frame->replyCode;
            if (empty($defaultErrorMsg)) {
                $errorMsg = "AMQP Error: \"{$errorCode} {$frame->replyText}.\" " .
                    "Class: {$className} ({$amqpClassName}:{$amqpMethodName})";
            } else {
                $errorMsg = "{$defaultErrorMsg} : " .
                    "AMQP Error: \"{$errorCode} {$frame->replyText}.\" " .
                    "Class: {$className} ({$amqpClassName}:{$amqpMethodName})";
            }

            switch ($frame->replyCode) {
                case 311:
                    throw new \Bunny\Exception\SoftError311Exception($errorMsg, $errorCode);
                case 312:
                    throw new \Bunny\Exception\SoftError312Exception($errorMsg, $errorCode);
                case 313:
                    throw new \Bunny\Exception\SoftError313Exception($errorMsg, $errorCode);
                case 403:
                    throw new \Bunny\Exception\SoftError403Exception($errorMsg, $errorCode);
                case 404:
                    throw new \Bunny\Exception\SoftError404Exception($errorMsg, $errorCode);
                case 405:
                    throw new \Bunny\Exception\SoftError405Exception($errorMsg, $errorCode);
                case 406:
                    throw new \Bunny\Exception\SoftError406Exception($errorMsg, $errorCode);
                case 320:
                    throw new \Bunny\Exception\HardError320Exception($errorMsg, $errorCode);
                case 402:
                    throw new \Bunny\Exception\HardError402Exception($errorMsg, $errorCode);
                case 501:
                    throw new \Bunny\Exception\HardError501Exception($errorMsg, $errorCode);
                case 502:
                    throw new \Bunny\Exception\HardError502Exception($errorMsg, $errorCode);
                case 503:
                    throw new \Bunny\Exception\HardError503Exception($errorMsg, $errorCode);
                case 504:
                    throw new \Bunny\Exception\HardError504Exception($errorMsg, $errorCode);
                case 505:
                    throw new \Bunny\Exception\HardError505Exception($errorMsg, $errorCode);
                case 506:
                    throw new \Bunny\Exception\HardError506Exception($errorMsg, $errorCode);
                case 530:
                    throw new \Bunny\Exception\HardError530Exception($errorMsg, $errorCode);
                case 540:
                    throw new \Bunny\Exception\HardError540Exception($errorMsg, $errorCode);
                case 541:
                    throw new \Bunny\Exception\HardError541Exception($errorMsg, $errorCode);
            }
        } else {
            $errorCode = 0;
            if (empty($defaultErrorMsg)) {
                $errorMsg = "AMQP Error in: {$className} ({$amqpClassName}:{$amqpMethodName})";
            } else {
                $errorMsg = "{$defaultErrorMsg} : AMQP Error in: " .
                    "{$className} ({$amqpClassName}:{$amqpMethodName})";
            }
        }

        // Handle all other exceptions based on the class type
        switch ($classType) {
            case 'client':
                return new ClientException($errorMsg, $errorCode);
            case 'channel':
                return new ChannelException($errorMsg, $errorCode);
            default:
                return new BunnyException($errorMsg, $errorCode);
        }
    }

    /**
     * Returns the class details for the given class id,
     * returns null if class was not found.
     *
     * @param int $classId
     *
     * @return array|null
     */
    protected function getClass($classId)
    {
        switch ($classId) {
            // connection
            case 10:
                return [
                    'name'    => 'connection',
                    'type'    => 'client',
                    'methods' => [
                        10 => 'start',
                        11 => 'start-ok',
                        20 => 'secure',
                        21 => 'secure-ok',
                        30 => 'tune',
                        31 => 'tune-ok',
                        40 => 'open',
                        41 => 'open-ok',
                        50 => 'close',
                        51 => 'close-ok',
                        60 => 'blocked',
                        61 => 'unblocked'
                    ]
                ];

            // channel
            case 20:
                return [
                    'name'    => 'channel',
                    'type'    => 'channel',
                    'methods' => [
                        10 => 'open',
                        11 => 'open-ok',
                        20 => 'flow',
                        21 => 'flow-ok',
                        40 => 'close',
                        41 => 'close-ok'
                    ]
                ];

            // access
            case 30:
                return [
                    'name'    => 'access',
                    'type'    => 'channel',
                    'methods' => [
                        10 => 'request',
                        11 => 'request-ok'
                    ]
                ];

            // exchange
            case 40:
                return [
                    'name'    => 'exchange',
                    'type'    => 'channel',
                    'methods' => [
                        10 => 'declare',
                        11 => 'declare-ok',
                        20 => 'delete',
                        21 => 'delete-ok',
                        30 => 'bind',
                        31 => 'bind-ok',
                        40 => 'unbind',
                        51 => 'unbind-ok'
                    ]
                ];

            // queue
            case 50:
                return [
                    'name'    => 'queue',
                    'type'    => 'channel',
                    'methods' => [
                        10 => 'declare',
                        11 => 'declare-ok',
                        20 => 'bind',
                        21 => 'bind-ok',
                        30 => 'purge',
                        31 => 'purge-ok',
                        40 => 'delete',
                        41 => 'delete-ok',
                        50 => 'unbind',
                        51 => 'unbind-ok'
                    ]
                ];

            // basic
            case 60:
                return [
                    'name'    => 'basic',
                    'type'    => 'channel',
                    'methods' => [
                        10 => 'qos',
                        11 => 'qos-ok',
                        20 => 'consume',
                        21 => 'consume-ok',
                        30 => 'cancel',
                        31 => 'cancel-ok',
                        40 => 'publish',
                        50 => 'return',
                        60 => 'deliver',
                        70 => 'get',
                        71 => 'get-ok',
                        72 => 'get-empty',
                        80 => 'ack',
                        90 => 'reject',
                        100 => 'recover-async',
                        110 => 'recover',
                        111 => 'recover-ok',
                        120 => 'nack'
                    ]
                ];

            // tx
            case 90:
                return [
                    'name'    => 'tx',
                    'type'    => 'channel',
                    'methods' => [
                        10 => 'select',
                        11 => 'select-ok',
                        20 => 'commit',
                        21 => 'commit-ok',
                        30 => 'rollback',
                        31 => 'rollback-ok'
                    ]
                ];

            // confirm
            case 85:
                return [
                    'name'    => 'confirm',
                    'type'    => 'channel',
                    'methods' => [
                        10 => 'select',
                        11 => 'select-ok'
                    ]
                ];


            default:
                return null;
        }
    }

    /**
     * Returns the method name for the given class (as called by getClass())
     * and method id, returns the $default if not found.
     *
     * @param array  $class
     * @param int    $methodId
     * @param string $default
     *
     * @return string
     */
    protected function getClassMethod($class, $methodId, $default = 'unknown')
    {
        return $class['methods'][$methodId] ?? $default;
    }

}

