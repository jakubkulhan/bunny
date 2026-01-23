<?php

declare(strict_types=1);

namespace Bunny;

final class Defaults
{
    public const HOST = '127.0.0.1';
    public const PORT = 5672;
    public const VHOST = '/';
    public const USER = 'guest';
    public const PASSWORD = 'guest';
    public const TIMEOUT = 1.0;
    public const HEARTBEAT = 60.0;
    public const HEARTBEAT_CALLBACK = null;
    public const TLS = [];
    public const CLIENT_PROPERTIES = [];
    public const CONNECTOR = null;
}
