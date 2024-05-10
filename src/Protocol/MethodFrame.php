<?php

declare(strict_types=1);

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
    public int $classId;

    public int $methodId;

    public function __construct(int $classId, int $methodId)
    {
        parent::__construct(Constants::FRAME_METHOD);
        $this->classId = $classId;
        $this->methodId = $methodId;
    }

}
