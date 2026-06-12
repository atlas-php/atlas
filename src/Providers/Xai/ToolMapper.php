<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai;

use Atlasphp\Atlas\Providers\Responses\ToolMapper as ResponsesToolMapper;

/**
 * Maps Atlas tool definitions to xAI's Responses-compatible format.
 *
 * Identical to the Responses base except for tool-call id resolution: some xAI
 * grok models return a bare sequential index as `call_id`, so the real id lives
 * in the `id` field instead.
 */
class ToolMapper extends ResponsesToolMapper
{
    /**
     * Prefer `call_id`, but fall back to `id` when `call_id` is missing or a
     * bare numeric index (e.g. "0", "1") rather than a proper "call_XXXX".
     *
     * @param  array<string, mixed>  $item
     */
    protected function resolveCallId(array $item): string
    {
        $callId = $item['call_id'] ?? '';

        if ($callId !== '' && ! is_numeric($callId)) {
            return $callId;
        }

        return $item['id'] ?? $callId;
    }
}
