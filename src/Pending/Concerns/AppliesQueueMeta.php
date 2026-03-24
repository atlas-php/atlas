<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

/**
 * Shared queue meta injection for executeFromPayload methods.
 *
 * All queueable request builders need to restore meta from the serialized
 * payload and inject the execution_id when running inside a tracked execution.
 */
trait AppliesQueueMeta
{
    /**
     * Apply queue meta (including execution_id) to a rebuilt request.
     *
     * @param  object  $request  The fluent builder instance (must have withMeta)
     * @param  array<string, mixed>  $payload  Deserialized queue payload
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     */
    protected static function applyQueueMeta(object $request, array $payload, ?int $executionId): void
    {
        $meta = $payload['meta'] ?? [];

        if ($executionId !== null) {
            $meta['execution_id'] = $executionId;
        }

        if (! empty($meta)) {
            $request->withMeta($meta);
        }
    }
}
