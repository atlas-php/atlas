<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events\Concerns;

/**
 * Caps the string payloads broadcast by the tool-call orchestration events to the
 * `atlas.broadcast.max_tool_payload_length` limit, keeping a large tool result,
 * argument, or error from exceeding the socket transport's per-message size cap
 * (Pusher ~10KB; Reverb configurable) — which would otherwise drop the event or
 * close the connection.
 *
 * The cap is a **byte** length (mb_strcut, never splitting a multibyte char), so
 * the result is safe to put on a byte-limited transport. A value of 0 or less (or
 * an unset config) disables the cap. Default is 2048 bytes.
 */
trait CapsBroadcastPayload
{
    protected function capBroadcastPayload(string $value): string
    {
        $max = $this->maxBroadcastPayloadBytes();

        return $max > 0 ? mb_strcut($value, 0, $max) : $value;
    }

    /**
     * Cap an arbitrary tool-argument value. Strings are byte-truncated; a
     * non-string (e.g. a nested array) is left as-is unless its JSON form exceeds
     * the cap, in which case the truncated JSON is sent so a large nested
     * structure can't blow past the transport limit.
     */
    protected function capBroadcastValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->capBroadcastPayload($value);
        }

        $max = $this->maxBroadcastPayloadBytes();

        if ($max <= 0) {
            return $value;
        }

        $encoded = json_encode($value);

        if ($encoded === false || strlen($encoded) <= $max) {
            return $value;
        }

        return mb_strcut($encoded, 0, $max);
    }

    private function maxBroadcastPayloadBytes(): int
    {
        return (int) config('atlas.broadcast.max_tool_payload_length', 2048);
    }
}
