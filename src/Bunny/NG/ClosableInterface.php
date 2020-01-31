<?php
namespace Bunny\NG;

/**
 * Resource that must be explicitly closed.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
interface ClosableInterface
{

    /**
     * Closes the resource.
     *
     * After the resource is closed behavior of any of its method is undefined.
     *
     * If the resource is not closed by calling this method, it will trigger an error in its destructor.
     *
     * @return void
     */
    public function close();

}
