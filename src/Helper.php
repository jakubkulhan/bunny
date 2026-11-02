<?php

declare(strict_types=1);

namespace Bunny;

use Bunny\Helper\Async;
use Bunny\Helper\Sync;

final class Helper
{
    public static function async(Configuration $configuration): Async
    {
        return new Async($configuration);
    }

    public static function sync(Configuration $configuration): Sync
    {
        return new Sync(self::async($configuration));
    }
}
