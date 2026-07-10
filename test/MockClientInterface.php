<?php

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\ChannelInterface;
use Bunny\ClientInterface;

final class MockClientInterface implements ClientInterface
{
    private int $isConnectedCount = 0;
    private int $canDisconnectCount = 0;
    private int $disconnectCount = 0;
    private bool $isConnected;
    private bool $canDisconnect;

    public function __construct(bool $isConnected = true, bool $canDisconnect = true)
    {
        $this->isConnected = $isConnected;
        $this->canDisconnect = $canDisconnect;
    }

    public function channel(): ChannelInterface
    {
        return new MockChannelInterface();
    }

    public function disconnect(int $replyCode = 0, string $replyText = '', bool $connectionStatus = ClientInterface::RAW_CONNECTION_ACTIVE): void
    {
        $this->disconnectCount++;
    }

    public function isConnected(): bool
    {
        $this->isConnectedCount++;

        return $this->isConnected;
    }

    public function canDisconnect(): bool
    {
        $this->canDisconnectCount++;

        return $this->canDisconnect;
    }

    public function getIsConnectedCount(): int
    {
        return $this->isConnectedCount;
    }

    public function getCanDisconnectCount(): int
    {
        return $this->canDisconnectCount;
    }

    public function getDisconnectCount(): int
    {
        return $this->disconnectCount;
    }
}
