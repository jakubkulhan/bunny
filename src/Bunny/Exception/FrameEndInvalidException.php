<?php
namespace Bunny\Exception;

/**
 * Peer sent invalid frame end byte.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class FrameEndInvalidException extends ProtocolException
{

    public function __construct()
    {
        parent::__construct("AbstractFrame end byte is invalid.");
    }

}
