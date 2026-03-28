<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware;

/**
 * Context for provider-layer middleware.
 *
 * Wraps every HTTP call to an AI provider. Available for all modalities:
 * text, stream, structured, image, audio, video, embed, moderate.
 */
class ProviderContext
{
    /**
     * @param  array<string, mixed>  $meta  Populated from the request object's meta by Driver::dispatch(). Mutable for cross-middleware data passing. After construction, this is the authoritative field — mutations here are not reflected back into $request->meta.
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $method,
        public mixed $request,
        public array $meta = [],
    ) {}
}
