<?php

declare(strict_types=1);

namespace Bunny;

interface ClientInterface
{
    public const RAW_CONNECTION_ACTIVE = true;
    public const RAW_CONNECTION_INACTIVE = false;

    public function channel(): ChannelInterface;

    public function disconnect(int $replyCode = 0, string $replyText = '', bool $connectionStatus = ClientInterface::RAW_CONNECTION_ACTIVE): void;

    public function isConnected(): bool;

    public function canDisconnect(): bool;
}
