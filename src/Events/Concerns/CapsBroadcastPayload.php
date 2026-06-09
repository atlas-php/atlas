<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events\Concerns;

/**
 * Caps a string payload broadcast by the tool-call orchestration events to the
 * `atlas.broadcast.max_tool_payload_length` limit, keeping a large tool result
 * or argument from exceeding the socket transport's message-size cap. With no
 * limit configured (the default), or a value of 0 or less, the value is
 * broadcast in full — nothing is hidden from a live trace view.
 *
 * The cap is a character count (mb_substr), not a byte count.
 */
trait CapsBroadcastPayload
{
    protected function capBroadcastPayload(string $value): string
    {
        $max = (int) config('atlas.broadcast.max_tool_payload_length');

        return $max > 0 ? mb_substr($value, 0, $max) : $value;
    }
}
