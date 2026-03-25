<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events\Concerns;

use Illuminate\Broadcasting\Channel;

/**
 * Shared broadcasting logic for stream events that target a single channel.
 *
 * @property Channel $channel
 */
trait BroadcastsOnChannel
{
    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [$this->channel];
    }
}
