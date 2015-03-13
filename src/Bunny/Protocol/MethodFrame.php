<?php
namespace Bunny\Protocol;

use Bunny\Constants;

/**
 * Method AMQP frame.
 *
 * Frame's payload wire format:
 *
 *
 *         0          2           4
 *     ----+----------+-----------+--------------------
 *     ... | class-id | method-id | method-arguments...
 *     ----+----------+-----------+--------------------
 *            uint16     uint16
 *
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodFrame extends AbstractFrame
{

    /** @var int */
    public $classId;

    /** @var int */
    public $methodId;

    public function __construct($classId = null, $methodId = null)
    {
        parent::__construct(Constants::FRAME_METHOD);
        $this->classId = $classId;
        $this->methodId = $methodId;
    }

}
