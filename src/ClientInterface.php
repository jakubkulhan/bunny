<?php

declare(strict_types=1);

namespace Bunny;

interface ClientInterface
{
    public function channel(): ChannelInterface;

    public function disconnect(int $replyCode = 0, string $replyText = ''): void;

    public function isConnected(): bool;
}
