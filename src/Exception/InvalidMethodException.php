<?php
namespace Bunny\Exception;

/**
 * Peer sent frame with invalid method id.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class InvalidMethodException extends ProtocolException
{

    /** @var int */
    private $classId;

    /** @var int */
    private $methodId;

    public function __construct($classId, $methodId)
    {
        parent::__construct("Unhandled method frame method '{$methodId}' in class '{$classId}'.");
        $this->classId = $classId;
        $this->methodId = $methodId;
    }

    /**
     * @return int
     */
    public function getClassId()
    {
        return $this->classId;
    }

    /**
     * @return int
     */
    public function getMethodId()
    {
        return $this->methodId;
    }

}
