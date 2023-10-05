<?php

declare(strict_types=1);

namespace Bunny;

final class Channels
{
    /**
     * @var array<int, Channel>
     */
    private array $channels = [];

    public function set(int $channelid, Channel $channel): void
    {
        $this->channels[$channelid] = $channel;
    }

    public function has(int $channelid): bool
    {
        return array_key_exists($channelid, $this->channels);
    }

    public function get(int $channelid): Channel
    {
        return $this->channels[$channelid];
    }

    public function unset(int $channelid): void
    {
        unset($this->channels[$channelid]);
    }


    /**
     * @return iterable<int, Channel>
     */
    public function all(): iterable
    {
        yield from $this->channels;
    }
}
