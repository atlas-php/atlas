<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Provider middleware scoped to embedding and analysis calls (embed, moderate, rerank).
 *
 * Implement this interface to run middleware only on embedding-related provider calls.
 * Receives ProviderContext with method 'embed', 'moderate', or 'rerank'.
 */
interface EmbedMiddleware extends ProviderMiddleware {}
