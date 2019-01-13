<?php

namespace Bunny\Protocol;

use Bunny\Exception\FrameException;

/**
 * AMQP-0-9-1 Frame Error fetcher. 
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * Returns the appropriate exception instance based on the frame type.
 *
 * For MethodChannelCloseFrame, MethodConnectionCloseFrame and MethodBasicReturnFrame frames
 * a unique SoftError???Exception or HardError???Exception will be returned as these
 * frames each have a replyCode and replyText property.
 *
 * For all other frames either a FrameException instance will be returned.
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
     * @param AbstractFrame $frame
     * @param string        $defaultErrorMsg
     *
     * @return \Bunny\Exception\FrameException
     */
    public function get(AbstractFrame $frame, $defaultErrorMsg = '')
    {
        if ($frame instanceof MethodChannelCloseFrame ||
            $frame instanceof MethodConnectionCloseFrame ||
            $frame instanceof MethodBasicReturnFrame) {

            // These frames all have replyCode and replyText properties
            $errorMsg = $this->buildErrorMsg(
                get_class($frame),
                $defaultErrorMsg,
                $frame->classId,
                $frame->methodId,
                $frame->replyCode,
                $frame->replyText
            );

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $this->getFrameException($errorMsg, $frame->replyCode);

        } else if ($frame instanceof MethodFrame) {
            // These frames don't have replyCode and replyText properties but they do
            // have classId and methodId properties.
            $errorMsg = $this->buildErrorMsg(
                get_class($frame),
                $defaultErrorMsg,
                $frame->classId,
                $frame->methodId
            );

            return new FrameException($errorMsg);
        } else {
            // These frames don't have any useful details for us to use.
            $errorMsg = $this->buildErrorMsg(
                get_class($frame),
                $defaultErrorMsg
            );

            return new FrameException($errorMsg);
        }
    }

    /**
     * Constructs an error message based on the supplied data.
     *
     * @param string      $frameClass
     * @param string      $defaultErrorMsg
     * @param int|null    $classId
     * @param int|null    $methodId
     * @param int|null    $replyCode
     * @param string|null $replyText
     *
     * @return string
     */
    protected function buildErrorMsg($frameClass, $defaultErrorMsg = '',
                                     $classId = null, $methodId = null,
                                     $replyCode = null, $replyText = null)
    {
        if (empty($defaultErrorMsg)) {
            $errPrefix = '';
        } else {
            $errPrefix = $defaultErrorMsg . ': ';
        }

        if ($methodId !== null && $classId !== null) {
            $amqpClass = $this->getClass($classId);
            if ($amqpClass === null) {
                $amqpClassName = 'unknown';
                $amqpMethodName = 'unknown';
            } else {
                $amqpClassName = $amqpClass['name'];
                $amqpMethodName = $this->getClassMethod($amqpClass, $methodId);
            }
        } else {
            $amqpClassName = '';
            $amqpMethodName = '';
        }

        if ($methodId !== null && $classId !== null && $replyCode !== null && $replyText !== null) {
            // Full error message
            return sprintf('%sAMQP Error: "%s %s" in class %s (%s:%s).',
                $errPrefix,
                $replyCode,
                $replyText,
                $frameClass,
                $amqpClassName,
                $amqpMethodName
            );
        } else if ($methodId !== null && $classId !== null && $replyCode === null && $replyText === null) {
            // Full error message except for replyCode and replyText
            return sprintf('%sAMQP Error in class %s (%s:%s).',
                $errPrefix,
                $frameClass,
                $amqpClassName,
                $amqpMethodName
            );
        } else {
            // Bare-bones error message, we only have the frame class.
            return sprintf('%sAMQP Error in class %s.',
                $errPrefix,
                $frameClass
            );
        }
    }

    /**
     * Returns the appropriate exception for the given reply code.
     *
     * @param string $errorMsg
     * @param int    $replyCode
     *
     * @return \Exception
     */
    protected function getFrameException($errorMsg, $replyCode)
    {
        switch ($replyCode) {
            case 311:
                return new \Bunny\Exception\FrameSoftError311Exception($errorMsg, $replyCode);
            case 312:
                return new \Bunny\Exception\FrameSoftError312Exception($errorMsg, $replyCode);
            case 313:
                return new \Bunny\Exception\FrameSoftError313Exception($errorMsg, $replyCode);
            case 403:
                return new \Bunny\Exception\FrameSoftError403Exception($errorMsg, $replyCode);
            case 404:
                return new \Bunny\Exception\FrameSoftError404Exception($errorMsg, $replyCode);
            case 405:
                return new \Bunny\Exception\FrameSoftError405Exception($errorMsg, $replyCode);
            case 406:
                return new \Bunny\Exception\FrameSoftError406Exception($errorMsg, $replyCode);
            case 320:
                return new \Bunny\Exception\FrameHardError320Exception($errorMsg, $replyCode);
            case 402:
                return new \Bunny\Exception\FrameHardError402Exception($errorMsg, $replyCode);
            case 501:
                return new \Bunny\Exception\FrameHardError501Exception($errorMsg, $replyCode);
            case 502:
                return new \Bunny\Exception\FrameHardError502Exception($errorMsg, $replyCode);
            case 503:
                return new \Bunny\Exception\FrameHardError503Exception($errorMsg, $replyCode);
            case 504:
                return new \Bunny\Exception\FrameHardError504Exception($errorMsg, $replyCode);
            case 505:
                return new \Bunny\Exception\FrameHardError505Exception($errorMsg, $replyCode);
            case 506:
                return new \Bunny\Exception\FrameHardError506Exception($errorMsg, $replyCode);
            case 530:
                return new \Bunny\Exception\FrameHardError530Exception($errorMsg, $replyCode);
            case 540:
                return new \Bunny\Exception\FrameHardError540Exception($errorMsg, $replyCode);
            case 541:
                return new \Bunny\Exception\FrameHardError541Exception($errorMsg, $replyCode);
            default:
                return new FrameException($errorMsg, $replyCode);
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

