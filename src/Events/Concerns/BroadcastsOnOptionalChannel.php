<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events\Concerns;

use Illuminate\Broadcasting\Channel;

/**
 * Shared broadcasting logic for events that optionally target a channel.
 *
 * When no channel is set, broadcasting is suppressed via broadcastWhen().
 * Used by orchestration events where broadcasting is opt-in via the executor.
 *
 * @property Channel|null $channel
 */
trait BroadcastsOnOptionalChannel
{
    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return $this->channel !== null ? [$this->channel] : [];
    }

    public function broadcastWhen(): bool
    {
        return $this->channel !== null;
    }
}
