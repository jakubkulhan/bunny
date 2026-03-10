<?php

declare(strict_types=1);

namespace Bunny\Test;


use Bunny\ChannelInterface;
use Bunny\ClientInterface;

final class MockClientInterface implements ClientInterface
{
    private int $disconnectCount = 0;
    private bool $isConnected;

    public function __construct(bool $isConnected)
    {
        $this->isConnected = $isConnected;
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
        return $this->isConnected;
    }

    public function getDisconnectCount(): int
    {
        return $this->disconnectCount;
    }
}
