<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Provider middleware scoped to text modality calls (text, stream, structured).
 *
 * Implement this interface to run middleware only on text-related provider calls.
 * Receives ProviderContext with method 'text', 'stream', or 'structured'.
 */
interface TextMiddleware extends ProviderMiddleware {}
